<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report {{ $report->report_title }}</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000;background: #c7e0b5; padding: 8px; text-align: left; }
        th { background-color: #75ae4c; }
        h1, h2 { margin-bottom: 10%; }
        h2 { font-size: 24px; background-color: #75ae4c}
        .page-break {
            page-break-after: always;
        }

        h1, h2, h3, p {
            margin: 10px 0; /* Reduce margin to avoid excessive spacing */
        }
        p {
            margin-bottom: 8px !important;
            text-align: justify;
        }
        .cover-page {
            text-align: center;
        }
        .cover-page b {
            display: block;
        }
        .cover-page div, .cover-page h1 {
            margin-bottom: 200px;
            background: none;
        }
        .cover-page h2 {
            margin-bottom: 75px;
            background: none;
        }
        .list-of-content {
            border: none;
        }
        
        .list-of-content td, .list-of-content th{
            border: none;
            background: none;
        }
        .list-of-content th {
            color: #fff;
            background-color: #75ae4c;
        }
        .content-table .row {
    	height: 16px;
        border-bottom:3px dotted black;
        position: relative;
        font-size: 18px;
        margin-bottom: 10px;
    	}
        .content-table .row div {
          position: absolute;
          top:0px;
          background: #fff;
          height:19px;
          
          padding:0px 1px;
          box-sizing: content-box;
        }
        .content-table .row div.left {
        	left: 0px;
            padding-right:11px;
            }
        .content-table .row div.right {
         	right: 0px;
            padding-left:11px;
         }
    </style>
</head>
<body>
    <div style="" class="cover-page">
        <img style="display:block;width:15%;margin:auto" src="data:image/jpeg;base64,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"/>
        
        
        <h1>{{ $report->report_title }}</h1>
        
        <h2><b>for:</b>{{ $report->property->property_name }}</h2>
        
        <h2><b>owner name:</b>{{ $report->property->property_name }}</h2>
        
        <div><b>Auditor name</b>{{ $report->auditor_name }}</div>
        <div>{{ $report->created_at->format('Y-m-d') }}</div>
    </div>

    <h2>Abbreviations</h2>
    <table>
        <thead>
            <tr>
                <th>Abbreviation</th>
                <th>Meaning</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($abbreviations as $abbreviation)
                <tr>
                    <td>{{ $abbreviation->abbreviation }}</td>
                    <td>{{ $abbreviation->meaning }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <br/>
    <br/>
    <h2>
        TARIFF VALUES
    </h2>
    <table>
        <thead>
            <tr>
                <th>Energy source</th>
                <th>Unit</th>
                <th>Cost (NIS)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tarrifValuesTable as $key=>$tarrif)
                <tr>
                    <td>{{ $key }}</td>
                    <td>{{ $tarrif['unit'] }}</td>
                    <td>{{ number_format($tarrif['cost'], 2) }} NIS</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="page-break"></div>
    <h2>Executive Summary</h2>
    <p>{!! $summary?->content !!}</p>
    <div>## todo: Add saving calc table Page 8 in Jenin's report</div>
    <div class="page-break"></div>
    <h1>GENERAL DESCRIPTION</h1>
    <h2>Introduction</h2>
    <p>{!! $introduction?->content !!}</p>
    <h2>{{ $report->property->property_name }} site</h2>
    <div>{{ $report->property->property_name }} is a {{ $report->property->property_usage }} {{$report->property->property_type}}, located in {{ $report->property->property_address }}.The {{$report->property->property_type}} was established in 2015 and consists
of {{ $report->property->number_of_floors }} floors. with overall area of {{ $report->property->property_area }} meter square, and total of {{ $report->property-> number_of_rooms }} rooms</div>
    <div class="page-break"></div>
    <h2>Property Devices</h2>
    @foreach ($groupedDevices as $categoryId => $devices)
        <h3>{{ $devices->first()->category->lookup_value }}</h3>
        <p>{{$descriptionsnData[$categoryId]}}</p>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Type</th>
                    <th>Wattage (kWh)</th>
                    <th>Quantity</th>
                    <th>Operational Hours (Annual)</th>
                    <th>Total Consumption (kWh/year)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($devices as $device)
                    <tr>
                        <td>{{ $device->description }}</td>
                        <td>{{ $device->device_key }}</td>
                        <td>{{ $device->power }}</td>
                        <td>{{ $device->quantity }}</td>
                        <td>{{ $device->operation_hours }}</td>
                        <td>{{ $device->total_consumption }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
    <div class="page-break"></div>
    <h2>Electricity Balance</h2>
    <h6>Percentage of Energy Consumption in Different Systems Consumption</h6>
    <table>
        <thead>
            <tr>
                <th>Load type</th>
                <th>Total Power Consumption (kWh)</th>
                <th>Percentage of Total Consumption</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($categoryConsumption as $data)
                <tr>
                    <td>{{$groupedDevices[$data['category_id']]->first()->category->lookup_value}}</td>
                    <td>{{ number_format($data['total'], 2) }}</td>
                    <td>{{ number_format($data['percentage'], 2) }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <h2>Power Consumption Pie Chart</h2>
    <div class="page-break"></div>
    <h2>Recommendations to Enhanced Power Consumption</h2>
    <div>
            @foreach ($recommendationData['recommendations'] as $key=>$recommendation)
                <div>
                    <div>{{$key}}</div>
                    <p>{{ $recommendation }}</p>
                </div>
            @endforeach
    </div>
    
    <div class="page-break"></div>
    <h2>Recommendations to Enhanced Power Consumption</h2>
    <div>
    @foreach ($recommendationTableCatDataObj as $key=>$recommendation)
    <h3>{{$key}}</h3>
    <table>
        <tr>
            <th>Category</th>
            <th>Current consumption (kwh)</th>
            <th>after recommendations (kwh)</th>
            <th>savings (kwh)</th>
        </tr>
        @foreach ($recommendation as $key=>$recommendation2)
                <tr>
                    <td>{{$key}}</td>
                    <td>{{$recommendation2['current_energy_use_kWh']}}</td>
                    <td>{{$recommendation2['energy_use_after_recommendations_kWh']}}</td>
                    <td>{{$recommendation2['saving_kWh']}}</td>
                </tr>
            @endforeach
        </table>
    @endforeach
    </div>
    <div class="page-break"></div>
    <h2>Recommendations to Enhanced Power Consumption</h2>
    <table>
        <tr>
            <th>Category</th>
            <th>Current consumption (kwh)</th>
            <th>after recommendations (kwh)</th>
            <th>savings (kwh)</th>
        </tr>
            @foreach ($recommendationTableDataObj as $key=>$recommendation)
                <tr>
                    <td>{{$key}}</td>
                    <td>{{$recommendation['current_energy_use_kWh']}}</td>
                    <td>{{$recommendation['energy_use_after_recommendations_kWh']}}</td>
                    <td>{{$recommendation['saving_kWh']}}</td>
                </tr>
            @endforeach
        </table>

    
    <div class="page-break"></div>
    <h2>Expected Savings</h2>
    <table>
        <thead>
            <tr>
                <th>Device</th>
                <th>Current Consumption (kWh/year)</th>
                <th>Recommended Consumption (kWh/year)</th>
                <th>Expected Savings (%)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($expectedSavingsTable as $saving)
                <tr>
                    <td>{{ $saving['device_name'] }}</td>
                    <td>{{ number_format($saving['current_consumption'], 2) }}</td>
                    <td>{{ number_format($saving['recommended_consumption'], 2) }}</td>
                    <td>{{ number_format($saving['savings_percentage'], 2) }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="page-break"></div>
    <h2>
        Understanding Energy Bills
    </h2>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th>Consumption (kWh)</th>
                <th>Cost (NIS)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($electricityBills as $key=>$bill)
                <tr>
                    <td>{{ $key }}</td>
                    <td>{{ $bill['kwh'] }}</td>
                    <td>{{ number_format($bill['cost'], 2) }} NIS</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div>
        <br>
        <br>
</body>
</html>
