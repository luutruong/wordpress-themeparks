!function (document, window) {
    document.onreadystatechange = function () {
        google.charts.load('current', {packages: ['corechart', 'bar']});
        google.charts.setOnLoadCallback(__drawBasic);

        function __drawBasic() {
            var elements = document.getElementsByClassName('js-chart-element');
            for (var i = 0; i < elements.length; i++) {
                var element = elements[i];
                _draw_chart(element);
            }
        }

        function _draw_chart(element) {
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'X');
            data.addColumn('number', 'Minutes');
            data.addRows(JSON.parse(element.getAttribute('data-wait')));

            var options = {
                hAxis: {
                    title: element.getAttribute('data-haxis-title'),
                    viewWindow: {
                        min: [7, 30, 0],
                        max: [17, 30, 0]
                    }
                },
                vAxis: {
                    title: element.getAttribute('data-vaxis-title'),
                },
                legend: {position: 'none'},
                theme: {
                    chartArea: {width: '80%', height: '70%'}
                },
                annotations: {
                    alwaysOutside: true,
                    textStyle: {
                        fontSize: 14,
                        color: '#000',
                        auraColor: 'none'
                    }
                },
                title: element.getAttribute('data-wait-date')
            };

            var chart = new google.visualization.ColumnChart(element);
            chart.draw(data, options);
        }
    }
}
(document, this);
