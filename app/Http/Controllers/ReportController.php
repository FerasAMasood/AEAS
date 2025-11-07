<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;
use Dompdf\Dompdf;
use Dompdf\Options;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;


class ReportController extends Controller
{
    public function index()
    {
        $reports =  Report::with('property')->get(); 
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
    
        $report = Report::create($validated);
    
        return response()->json(['message' => 'Report created successfully', 'report' => $report], 201);
    }
    
    public function show($id)
    {
        $report = Report::with('property')->findOrFail($id);
        return response()->json($report);
    }

    public function update(Request $request, $id)
    {
        $report = Report::findOrFail($id);
        
        $validated = $request->validate([
            'property_id' => 'exists:properties,id',
            'report_title' => 'string|max:255',
            'auditor_name' => 'string|max:255',
            'date' => 'date',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif', // Image validation
        ]);

        if ($request->hasFile('cover_image')) {
           
            $validated['cover_image'] = $request->file('cover_image')->store('reports', 'public');
        }

        $report->update($validated);
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

    // Send grouped devices to OpenAI API for recommendations
    $client = new Client();
    
    $response = $client->post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ',
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'model' => 'o1-mini',
            "messages" => [
                [
            'content' => "based on this data: ".json_encode($groupedDevices).", can you give specific recommendations for the electrical devices units that need improvement, plz make the recommendations as straight forward, economical and easy as possible and avoid genralatizations. Please consider any notes provided for each device when making recommendations. kindly respoend with json use description as the main key and put the recomendations as text in the value, give me a pragraph about each recomendation with energy saving calculations detailed and the total this has to be string every time, please dont use any special charachters or try to create tables just description", 
            //"Analyze the following grouped devices and recommend ways to enhance power consumption efficiency. Also, calculate the expected savings:\n" . json_encode($groupedDevices),
            'role'=>'user'
                ],
                
            ]
        ],
    ]);
    
    $responseData = json_decode($response->getBody(), true);
    
    //$recommendations = trim(str_replace(["```json", "```"], '',));
    $recommendations = trim(str_replace(["```json", "```"], '',$responseData['choices'][0]["message"]["content"]));


    // Parse recommendations and expected savings
    $recommendationData = [];
    $recommendationData["recommendations"] = json_decode($recommendations, true) ?? [];




    $client2 = new Client();
    $examples = [
        "The lighting system is one of the important systems in the building. A large number of lighting
units are spread throughout the building, including corridors and rooms. The building mainly
relies on 322 LED lighting units, in addition to 106 fluorescent units and some faulty units
totaling 55. Fluorescent lighting units are less energy-efficient compared to LED units. The
electricity consumption for the lighting system is 27,254.836 kWh per year, representing 14%
of the annual electricity consumption, with a cost of 18,510 NIS per year.Table (5) illustrates
the lighting consumption.",
    ];
    $message = "based on this data: ".json_encode($groupedDevices).", and this aggrigation of the same data (".json_encode($categoryConsumption)."), return a json object, the keys are the category id, and the values and a detailed description and disccussion of energy analysis for that category, without taking about saving options the value has to be a string paragraph, don't mention category id in the text. use the follwing as expamles. Example:";
    $message = "Based on this data: ".json_encode($groupedDevices)." and this aggregation of the same data (".json_encode($categoryConsumption)."), return a JSON object where the keys are the category IDs, and the values are detailed descriptions and discussions of energy analysis for each category. The description should:

Include paragraphs above the table that describe each device and its power consumption, along with the number of hours used. If notes are provided for any device, incorporate them into the description where relevant.
Mention only the highest-consuming device in cases where listing all devices would be unnecessary, especially if there are many devices in a category, to keep the table concise and focused on the most important information.
Highlight how much each system contributes as a percentage of the total power consumption, which should be part of the Electricity Balance. The value for each key must be a string paragraph. Do not mention the category ID in the text. Use the following as examples. please dont use any special charachters or try to create tables just description. Exmaple:";
    $message .= implode(". Example: ", $examples). ".";
    $response2 = $client2->post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer  ',
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'model' => 'o1-mini',
            "messages" => [
                [
            'content' => $message, 
            //"Analyze the following grouped devices and recommend ways to enhance power consumption efficiency. Also, calculate the expected savings:\n" . json_encode($groupedDevices),
            'role'=>'user'
            ]
            ]
        ],
    ]);

    $responseData2 = json_decode($response2->getBody(), true);
    
    //$recommendations = trim(str_replace(["```json", "```"], '',));
    $descriptions = trim(str_replace(["```json", "```"], '',$responseData2['choices'][0]["message"]["content"]));


    // Parse recommendations and expected savings
    $descriptionsnData = [];
    $descriptionsnData = json_decode($descriptions, true) ?? [];

   // return response()->json(["res"=>$descriptionsnData]);
   

















   $client3 = new Client();

   $message = "based on this data: ".json_encode($groupedDevices).", and this aggrigation of the same data (".json_encode($categoryConsumption)."), return a json object, the keys are the category id, and the values and a detailed description and disccussion of energy analysis for that category, without taking about saving options the value has to be a string paragraph, don't mention category id in the text. use the follwing as expamles. Example:";
   $message = "Based on this data: ".json_encode($groupedDevices)." and this aggregation of the same data (".json_encode($categoryConsumption)."), return a JSON object where the keys are the category names, 
   and the value is a json object that has the current energy use and the enery use after the recommendations and the saving, kindly use the keys: current_energy_use_kWh, energy_use_after_recommendations_kWh, saving_kWh. Please consider any notes provided for devices when calculating recommendations.";
   $message .= "and based on this recomendations: ".json_encode($recommendationData["recommendations"]);

   $response3 = $client3->post('https://api.openai.com/v1/chat/completions', [
       'headers' => [
           'Authorization' => 'Bearer  ',
           'Content-Type' => 'application/json',
       ],
       'json' => [
           'model' => 'o1-mini',
           "messages" => [
               [
           'content' => $message, 
           //"Analyze the following grouped devices and recommend ways to enhance power consumption efficiency. Also, calculate the expected savings:\n" . json_encode($groupedDevices),
           'role'=>'user'
           ]
           ]
       ],
   ]);

   $responseData3 = json_decode($response3->getBody(), true);
   
   $recommendationTableData = trim(str_replace(["```json", "```"], '',$responseData3['choices'][0]["message"]["content"]));


   // Parse recommendations and expected savings
   $recommendationTableDataObj = [];
   $recommendationTableDataObj = json_decode($recommendationTableData, true) ?? [];
   //return response()->json(['message' => 'Report created successfully', 'report' => $recommendationTableDataObj], 201);

   $client4 = new Client();

   $message = "based on this data: ".json_encode($groupedDevices).", and this aggrigation of the same data (".json_encode($categoryConsumption)."), return a json object, the keys are the category id, and the values and a detailed description and disccussion of energy analysis for that category, without taking about saving options the value has to be a string paragraph, don't mention category id in the text. use the follwing as expamles. Example:";
   $message = "Based on this data: ".json_encode($groupedDevices)." and this aggregation of the same data (".json_encode($categoryConsumption)."), return a JSON object where the keys are the category names, 
   and the value is a json object that has the current energy use and the energy use after the recommendations per device grouped by category, use device name as key in the sub object, and the saving, dont give recommendations for all devices just the high consumption or high saving, kindly use the keys: current_energy_use_kWh, energy_use_after_recommendations_kWh, saving_kWh. Please consider any notes provided for devices when making recommendations.";
   $message .= "and based on this recomendations: ".json_encode($recommendationData["recommendations"]);

   $response4 = $client4->post('https://api.openai.com/v1/chat/completions', [
       'headers' => [
           'Authorization' => 'Bearer  ',
           'Content-Type' => 'application/json',
       ],
       'json' => [
           'model' => 'o1-mini',
           "messages" => [
               [
           'content' => $message, 
           //"Analyze the following grouped devices and recommend ways to enhance power consumption efficiency. Also, calculate the expected savings:\n" . json_encode($groupedDevices),
           'role'=>'user'
           ]
           ]
       ],
   ]);

   $responseData4 = json_decode($response4->getBody(), true);
   
   $recommendationTableCatData = trim(str_replace(["```json", "```"], '',$responseData4['choices'][0]["message"]["content"]));


   // Parse recommendations and expected savings
   $recommendationTableCatDataObj = [];
   $recommendationTableCatDataObj = json_decode($recommendationTableCatData, true) ?? [];
   //return response()->json(['message' => 'Report created successfully', 'report' => $recommendationTableCatDataObj], 201);



    $expectedSavingsTable = $recommendationData['expected_savings'] ?? [];

    // Render HTML with additional data

    $keys = array_keys($tarrifValuesTable);
    unset($tarrifValuesTable[$keys[$report["id"]%count($tarrifValuesTable)]]);

    $html = view('reports.pdf', compact(
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
        'electricityBills',
        'descriptionsnData',
        'recommendationTableDataObj',
        'recommendationTableCatDataObj'
    ))->render();

    // Initialize Dompdf and render PDF
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    return $dompdf->stream('report_' . $report_id . '.pdf');
}

}
