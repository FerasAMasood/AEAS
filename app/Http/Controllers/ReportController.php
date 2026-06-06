<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Tariff;
use Illuminate\Http\Request;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\FrameDecorator\AbstractFrameDecorator;
use Dompdf\Options;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;


class ReportController extends Controller
{
    public function index()
    {
        $reports =  Report::with(['property', 'creator', 'updater'])->get(); 
        return response()->json($reports);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'report_title' => 'required|string|max:255',
            'auditor_name' => 'required|string|max:255',
            'date' => 'required|date',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif', // Image validation
        ]);
        
        if ($request->hasFile('cover_image')) {
           
            $validated['cover_image'] = $request->file('cover_image')->store('reports', 'public');
        }
    
        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;
        $report = Report::create($validated);
    
        return response()->json(['message' => 'Report created successfully', 'report' => $report->load(['creator', 'updater'])], 201);
    }
    
    public function show($id)
    {
        $report = Report::with(['property', 'creator', 'updater', 'abbreviations', 'tariffs.source'])->findOrFail($id);
        
        // Convert cover_image path to full URL if it exists
        if ($report->cover_image) {
            // Storage::url() returns a path like /storage/reports/filename.jpg
            // We need to prepend the full base URL
            $report->cover_image = $report->cover_image;
        }
        
        return response()->json($report);
    }

    public function update(Request $request, $id)
    {
        $report = Report::findOrFail($id);
        
        // Validate the request
        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'report_title' => 'required|string|max:255',
            'auditor_name' => 'required|string|max:255',
            'date' => 'required|date',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif',
        ]);

        // Handle cover image upload
        if ($request->hasFile('cover_image')) {
            $validated['cover_image'] = $request->file('cover_image')->store('reports', 'public');
        }

        // Set updated_by
        $validated['updated_by'] = $request->user()->id;
        
        // Update the report - explicitly set each field to ensure they're saved
        $report->property_id = $validated['property_id'];
        $report->report_title = $validated['report_title'];
        $report->auditor_name = $validated['auditor_name'];
        $report->date = $validated['date'];
        $report->updated_by = $validated['updated_by'];
        
        if (isset($validated['cover_image'])) {
            $report->cover_image = $validated['cover_image'];
        }
        
        $report->save();
        
        // Refresh to get latest data
        $report->refresh();
        
        // Load relationships
        $report->load(['creator', 'updater']);
        
        // Convert cover_image path to full URL if it exists
        if ($report->cover_image) {

            $report->cover_image = $report->cover_image;
        }
        
        return response()->json($report);
    }

    public function destroy($id)
    {
        $report = Report::findOrFail($id);
        $report->delete();

        return response()->json(['message' => 'Report deleted successfully']);
    }




    
//     public function generatePdf($report_id)
// {
//     // Find the report or fail
//     $report = Report::findOrFail($report_id);
    
//     // Fetch abbreviations
//     $abbreviations = $report->abbreviations()->get();
    
//     // Fetch summary
//     $summary = $report->summary; // Assuming the relationship is set
    
//     // Fetch introduction
//     $introduction = $report->introduction; // Assuming the relationship is set

//     // Fetch the property related to the report
//     $property = $report->property; // Assuming the relationship is set in the Report model

//     // Fetch property devices related to the property
//     $propertyDevices = $property->propertyDevices()->with('category')->with('device')->get(); // Assuming a relation in Property model

//     // Group devices by category
//     $groupedDevices = $propertyDevices->groupBy('category_id');
//     $property = $report->property; // Adjust this if necessary based on your relationships

//     // Fetch property devices with the category relationship
//     $propertyDevices = $property->propertyDevices()->with('category')->get();

//     // Calculate total power consumption for the property
//     $totalPropertyConsumption = $propertyDevices->sum('total_consumption');

//     // Group by category and calculate total power consumption per category
//     $categoryConsumption = $propertyDevices->groupBy('category_id')->map(function ($devices) {
//         return [
//             'total' => $devices->sum('total_consumption'),
//             'devices' => $devices, // Keep the devices for further details if needed
//         ];
//     });

//     // Prepare data for percentages
//     $categoryConsumption = $categoryConsumption->map(function ($data, $categoryId) use ($totalPropertyConsumption) {
//         return [
//             'total' => $data['total'],
//             'percentage' => $totalPropertyConsumption > 0 ? ($data['total'] / $totalPropertyConsumption) * 100 : 0,
          
//         ];
//     });
//     //return response()->json(['ti'=>$categoryConsumption]);
//     $pieChartHtml = view('reports.pie_chart', compact('categoryConsumption'))->render();
//     //return $pieChartHtml;
//     // Initialize Dompdf
//     $options = new Options();
//     $options->set('defaultFont', 'Arial');
//     $dompdf = new Dompdf($options);
    
//     // Load HTML content
//     $html = view('reports.pdf', compact('report', 'abbreviations', 'summary', 'introduction', 'groupedDevices', 'pieChartHtml', 'totalPropertyConsumption', 'categoryConsumption'))->render();

//     $dompdf->loadHtml($html);
    
//     // Set paper size and orientation
//     $dompdf->setPaper('A4', 'portrait');
    
//     // Render the PDF
//     $dompdf->render();
    
//     // Output the generated PDF to Browser
//     return $dompdf->stream('report_' . $report_id . '.pdf');
// }

public function generatePdf($report_id)
{
    // Find the report or fail
    $report = Report::findOrFail($report_id);
    $apiKey = "";
    $aiModel = "gpt-4o-mini";
    // Fetch abbreviations, summary, introduction, property, and property devices
    $abbreviations = $report->abbreviations()->get();
    $summary = $report->summary;
    $introduction = $report->introduction;
    $property = $report->property;
    $propertyDevices = $property->propertyDevices()->with('category')->with('device')->get();

    // Group devices by category
    $groupedDevices = $propertyDevices->groupBy('category_id');

    // Calculate total power consumption and category-wise data
    $totalPropertyConsumption = $propertyDevices->sum('total_consumption');
    $categoryConsumption = $propertyDevices->groupBy('category_id')->map(function ($devices) {
        return [
            'total' => $devices->sum('total_consumption'),
            'devices' => $devices,
        ];
    })->map(function ($data, $categoryId) use ($totalPropertyConsumption) {
        return [
            'total' => $data['total'],
            'category_id'=>$categoryId,
            'percentage' => $totalPropertyConsumption > 0 ? ($data['total'] / $totalPropertyConsumption) * 100 : 0,
        ];
    });

    $groupedDevicesJson = json_encode($groupedDevices);
    $categoryConsumptionJson = json_encode($categoryConsumption);

    $mergedPrompt = <<<PROMPT
You are an energy-audit assistant. You will receive GROUPED_DEVICES_JSON and CATEGORY_CONSUMPTION_JSON.

GROUPED_DEVICES_JSON:
{$groupedDevicesJson}

CATEGORY_CONSUMPTION_JSON:
{$categoryConsumptionJson}

Return one JSON object only (no markdown fences). Use these top-level keys:

1. "recommendations" — object. Each key is a device description string from the data; each value is one plain-text paragraph with specific, economical improvement ideas and qualitative energy-saving discussion. Every value must be a string. Use device notes when present. Avoid tables and special markup.

2. "category_descriptions" — object. Keys are category IDs as strings (e.g. "15"). Values are plain-text paragraphs: devices, power, operating hours, notes, and share of total consumption. Do not mention the raw category ID inside the paragraph. If many devices exist, focus on the highest consumers.

3. "recommendation_summary_by_category" — object. Keys are human-readable category names (e.g. "Lighting"). Values are objects with numeric fields: current_energy_use_kWh, energy_use_after_recommendations_kWh, saving_kWh. Base numbers on the data and on the same improvement story you use in "recommendations".

4. "recommendation_detail_by_category" — object. Keys are category names. Values are objects mapping device labels (only high consumption or high savings) to objects with current_energy_use_kWh, energy_use_after_recommendations_kWh, saving_kWh.

5. "expected_savings" — array of objects: device_name, current_consumption, recommended_consumption, savings_percentage (numbers where applicable). Use [] if none.

All sections must be internally consistent (same assumptions across tables and text).
PROMPT;

    $client = new Client();
    $response = $client->post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'model' => $aiModel,
            'messages' => [
                ['role' => 'user', 'content' => $mergedPrompt],
            ],
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 16384,
        ],
    ]);

    $responseData = json_decode($response->getBody(), true);
    $merged = $this->decodeOpenAiJsonObject($responseData);

    $recommendationData = [
        'recommendations' => is_array($merged['recommendations'] ?? null) ? $merged['recommendations'] : [],
        'expected_savings' => is_array($merged['expected_savings'] ?? null) ? $merged['expected_savings'] : [],
    ];
    $descriptionsnData = is_array($merged['category_descriptions'] ?? null) ? $merged['category_descriptions'] : [];
    $recommendationTableDataObj = is_array($merged['recommendation_summary_by_category'] ?? null)
        ? $merged['recommendation_summary_by_category']
        : [];
    $recommendationTableCatDataObj = is_array($merged['recommendation_detail_by_category'] ?? null)
        ? $merged['recommendation_detail_by_category']
        : [];


    $expectedSavingsTable = $recommendationData['expected_savings'] ?? [];

    // Energy saving opportunities for the report's property (for summary section table)
    $energySavingOpportunities = $property->energySavingOpportunities()->orderBy('sort_order')->get();

    // Render HTML with additional data
    $tarrifValuesTable = Tariff::where('report_id', $report_id)->with('source')->get();
    if ($tarrifValuesTable->isNotEmpty()) {
        $keys = $tarrifValuesTable->keys()->toArray();
        unset($tarrifValuesTable[$keys[$report->id % count($tarrifValuesTable)]]);
    }

    $viewData = compact(
        'report',
        'abbreviations',
        'summary',
        'introduction',
        'groupedDevices',
        'totalPropertyConsumption',
        'categoryConsumption',
        'expectedSavingsTable',
        'recommendationData',
        'tarrifValuesTable',
        'descriptionsnData',
        'recommendationTableDataObj',
        'recommendationTableCatDataObj',
        'energySavingOpportunities',
    );

    $viewData['tocPageNumbers'] = [];
    $htmlMeasure = view('reports.pdf', $viewData)->render();
    $tocPageNumbers = $this->measurePdfTocPageNumbers($htmlMeasure);

    $viewData['tocPageNumbers'] = $tocPageNumbers;
    $htmlFinal = view('reports.pdf', $viewData)->render();

    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($htmlFinal);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $this->addPdfPageNumberFooter($dompdf);

    return $dompdf->stream('report_'.$report_id.'.pdf');
}

    private function addPdfPageNumberFooter(Dompdf $dompdf): void
    {
        $font = $dompdf->getOptions()->getDefaultFont();
        $canvas = $dompdf->getCanvas();
        $footerSize = 9.0;
        $marginBottom = 28.0;

        $canvas->page_script(function (int $pageNum, int $pageCount, $cnv, FontMetrics $fm) use ($font, $footerSize, $marginBottom): void {
            $text = "Page {$pageNum} of {$pageCount}";
            $w = $cnv->get_width();
            $h = $cnv->get_height();
            $tw = $fm->getTextWidth($text, $font, $footerSize);
            $x = max(0.0, ($w - $tw) / 2);
            $y = $h - $marginBottom;
            $cnv->text($x, $y, $text, $font, $footerSize, [0.25, 0.25, 0.25]);
        });
    }

    /**
     * First Dompdf pass: record which PDF page each `id="pdf-toc-*"` anchor lands on.
     *
     * @return array<string, int>
     */
    private function measurePdfTocPageNumbers(string $html): array
    {
        $tocAnchorPages = [];
        $pageIndex = 0;

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        $dompdf->setCallbacks([
            [
                'event' => 'begin_page_render',
                'f' => function ($frame, $canvas, $fontMetrics) use (&$pageIndex, &$tocAnchorPages): void {
                    $pageIndex++;
                    $deco = $frame instanceof AbstractFrameDecorator
                        ? $frame
                        : $frame->get_decorator();
                    if ($deco instanceof AbstractFrameDecorator) {
                        $this->collectPdfTocAnchorsRecursive($deco, $pageIndex, $tocAnchorPages);
                    }
                },
            ],
        ]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $tocAnchorPages;
    }

    /**
     * @param  array<string, int>  $tocAnchorPages
     */
    private function collectPdfTocAnchorsRecursive(AbstractFrameDecorator $frame, int $pageNumber, array &$tocAnchorPages): void
    {
        $node = $frame->get_node();
        if ($node instanceof \DOMElement && $node->hasAttribute('id')) {
            $id = $node->getAttribute('id');
            if (str_starts_with($id, 'pdf-toc-')) {
                $key = substr($id, strlen('pdf-toc-'));
                if (! isset($tocAnchorPages[$key])) {
                    $tocAnchorPages[$key] = $pageNumber;
                }
            }
        }

        foreach ($frame->get_children() as $child) {
            $this->collectPdfTocAnchorsRecursive($child, $pageNumber, $tocAnchorPages);
        }
    }

    /**
     * @param  array<string, mixed>|null  $responseData
     * @return array<string, mixed>
     */
    private function decodeOpenAiJsonObject(?array $responseData): array
    {
        $content = $responseData['choices'][0]['message']['content'] ?? '';
        if (! is_string($content)) {
            return [];
        }
        $content = trim(str_replace(['```json', '```'], '', $content));
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }
}
