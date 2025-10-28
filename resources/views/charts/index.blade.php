<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chart Example</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <canvas id="myChart" width="400" height="400"></canvas>

    <script>
        var ctx = document.getElementById('myChart').getContext('2d');
        var myChart = new Chart(ctx, {
            type: 'pie', // or 'bar', 'line', etc.
            data: {
                labels: {!! json_encode($labels) !!},
                datasets: [{
                    label: 'My Data',
                    data: {!! json_encode($data) !!},
                    backgroundColor: ['#FF5733', '#33FF57', '#3357FF'], // Optional colors
                    borderColor: ['#FF5733', '#33FF57', '#3357FF'],
                    borderWidth: 1
                }]
            }
        });
    </script>
</body>
</html>
