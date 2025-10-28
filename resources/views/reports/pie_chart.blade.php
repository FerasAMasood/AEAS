<script src="https://d3js.org/d3.v6.min.js"></script>
<div id="chart"></div>

<script>
    const data = {!! json_encode($categoryConsumption) !!}; // Ensure this is being populated
    
    // Transform data for D3
    const transformedData = Object.keys(data).map(key => ({
        category_id: key,
        total: data[key].total,
        percentage: data[key].percentage,
    }));

    const width = 400;
    const height = 400;
    const radius = Math.min(width, height) / 2;

    const color = d3.scaleOrdinal(d3.schemeCategory10);

    const svg = d3.select("#chart")
        .append("svg")
        .attr("width", width)
        .attr("height", height)
        .append("g")
        .attr("transform", `translate(${width / 2}, ${height / 2})`);

    const pie = d3.pie().value(d => d.total);
    const arc = d3.arc().innerRadius(0).outerRadius(radius);

    const g = svg.selectAll(".arc")
        .data(pie(transformedData))
        .enter().append("g")
        .attr("class", "arc");

    g.append("path")
        .attr("d", arc)
        .style("fill", (d) => color(d.data.category_id)); // Use category_id for color mapping

    g.append("text")
        .attr("transform", (d) => `translate(${arc.centroid(d)})`)
        .attr("dy", ".35em")
        .text((d) => d.data.category_id); // Display category_id on the pie chart

    const legend = svg.selectAll(".legend")
        .data(pie(transformedData))
        .enter().append("g")
        .attr("class", "legend")
        .attr("transform", (d, i) => `translate(-40, ${i * 20 - height / 2 + 40})`); // Adjust position of legend

    legend.append("rect")
        .attr("x", width - 18)
        .attr("width", 18)
        .attr("height", 18)
        .style("fill", (d) => color(d.data.category_id));

    legend.append("text")
        .attr("x", width - 24)
        .attr("y", 9)
        .attr("dy", ".35em")
        .style("text-anchor", "end")
        .text(d => `Category ID: ${d.data.category_id} - Total: ${d.data.total.toFixed(2)} (${d.data.percentage.toFixed(2)}%)`);
</script>
