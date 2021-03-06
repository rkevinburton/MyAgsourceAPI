//var var_arr_graph_colors = ['#FF3C3C','#FF5A5A','#FF7878','#FF9696','#FFB4B4']; //monochrome
//var var_arr_graph_colors = ['#643b3b', '#825a5a', '#a07878', '#bd9696', '#dcb2b2'];  //monochrome
//var var_arr_graph_colors = ['#F15928', '#585C5F', '#08A04A', '#006C70', '#98E8F9']; //dpn?
//var var_arr_graph_colors = ['#00838C', '#939E77', '#B03500', '#BA91A8', '#97C4A4']; 
//var var_arr_graph_colors = ['#D54C18', '#48495B', '#264071', '#9CA294'];
var var_arr_graph_colors = ['#E4B577', '#75C4E4', '#B6B6A5', '#E07F8D', '#97C4A4', '#FF9168']; 

//get current base url--used only on this page
if (!window.location.origin) window.location.origin = window.location.protocol+"//"+window.location.host;
var pathArray = window.location.href.split( '/' );
var server_path = (typeof(pathArray[3]) == "string") ? pathArray[3] : '';


var global_options = {
	chart: {
		backgroundColor: null
	},
	title: {
		style: {
			color: '#EF5C29',
			fontWeight: 'bold'
		}
	},
	colors: [var_arr_graph_colors[0], var_arr_graph_colors[1], var_arr_graph_colors[2], var_arr_graph_colors[3], var_arr_graph_colors[4], var_arr_graph_colors[5]],
	credits: {
		href: window.location.origin + '/' + server_path + '/index.php',
		text: '© AgSource Cooperative Services'
	},
	exporting: {
		url: 'https://export.highcharts.com'
	},
	xAxis: [{
		gridLineColor : '#c0c0c0',
		categories: null,
		type: 'datetime'
	}],
	yAxis: [{
		allowDecimals: true,
		type: 'linear'
	}],
	legend : {
		margin: 0,
		borderRadius: 5,
		borderWidth: 1,
		reversed: false
	},
	tooltip : {
		xDateFormat: '%b %e, %Y'
	},
	plotOptions: {
		scatter: {
			tooltip: {
				xDateFormat: '%b %e, %Y'
			}
		}
	},
    series: [{
    }]
	// SET MORE THEME-RELATED VARIABLES (COLOR, ETC)?
};

Highcharts.setOptions(global_options);

/*
 * Called after base options are set in the options JSON object
 */

function get_chart_options(options_json, chart_type){
	switch(chart_type){
		case 'stacked area':
			return get_stacked_area_options(options_json);
			break;
		case 'scatter':
			return get_scatter_options(options_json);
			break;
		case 'stacked column':
			return get_stacked_column_options(options_json);
			break;
		case 'column':
			return get_column_options(options_json);
			break;
		case 'bar':
			return get_bar_options(options_json);
			break;
		case 'pie':
			return get_pie_options(options_json);
			break;
		case 'boxplot':
			return get_boxplot_options(options_json);
			break;
		case 'columnrange':
			return get_columnrange_options(options_json);
			break;
		default: //line
			return get_line_options(options_json);
			break;
	}
}


function get_stacked_area_options(options_json){
	options_json.chart.type = 'area';
	options_json.plotOptions.area = {
		marker: { 
			enabled: false
		},
		stacking: 'normal'
	};
	return options_json;
}

function get_stacked_column_options(options_json){
	options_json.chart.type = 'column';
	options_json.plotOptions.column = {
		'stacking': 'normal',
		'shadow': false,
		'borderWidth': 0
	};
	options_json.legend.reversed = true;
	return options_json;
}

function get_column_options(options_json){
	options_json.chart.type = 'column';
	options_json.plotOptions.column = {
		'stacking': null,
		'shadow': false,
		'borderWidth': 0
	};
	return options_json;
}

function get_bar_options(options_json){
	options_json.chart.type = 'bar';
	options_json.plotOptions.bar = {
        dataLabels: {
            enabled: true,
            align: 'right',
            color: '#C0C0C0'
         }
     };
	options_json.xAxis[0].type = 'linear';
	//options_json.xAxis.categories = [];
	return options_json;
}

function get_boxplot_options(options_json){
	options_json.chart.type = 'boxplot';
	options_json.plotOptions.boxplot = {
		grouping: false,
		whiskerWidth: 0,
        pointWidth: 8,
        lineWidth: 8,
        medianColor: null,
    	tooltip: {
    		pointFormat: '<span style="color:{point.color}">\u25CF</span> <b> {series.name}</b><br/>' + // docs
    			'Upper quartile: {point.q3}<br/>' +
    			'Median: {point.median}<br/>' +
    			'Lower quartile: {point.q1}<br/>'
    	},

	};
	return options_json;
}

function get_columnrange_options(options_json){
	options_json.chart.type = 'columnrange';
	options_json.plotOptions.columnrange = {
		grouping: false,
        pointWidth: 12,
    	tooltip: {
    		pointFormat: '<span style="color:{point.color}">\u25CF</span> <b> {series.name}</b><br/>' + // docs
    			'Upper Value: {point.high}<br/>' +
    			'Lower Value: {point.low}<br/>'
    	},

	};
	return options_json;
}

//@todo: many settings are specific to Inbreeding Distribution.  Additional charts will require this to be removed
function get_pie_options(options_json){
	options_json.chart.type = 'pie';
	options_json.plotOptions.pie = {
        allowPointSelect: true,
		dataLabels: {
			enabled: true,
			formatter: function(){return '<b>' + customFormatGtLt(this.point.name) + '</b>: ' + this.point.y + ' Animals<br>Distribution: ' + Math.round(this.point.percentage * 10)/10 + '%';},
			color: '#c0c0c0',
			style: {
				textShadow: false
			}
		}
    };
	options_json.tooltip = false; /*{
        pointFormat: '{series.name}: <b>{point.percentage:.1f}%</b>',
        useHTML:true
    };*/
	return options_json;
}

function get_line_options(options_json){
	options_json.chart.type = 'line';
	options_json.plotOptions.line = {
        dataLabels: {
			color: '#c0c0c0'
		}
    };
	return options_json;
}

function get_scatter_options(options_json){
	options_json.chart.type = 'scatter';
	options_json.plotOptions.scatter = {
		tooltip: {
			headerFormat: '<span style="font-size: 10px">{point.key}</span><br/>'
		}
	};
	return options_json;
}