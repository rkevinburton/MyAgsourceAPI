var arr_sort_by = new Array();
var arr_sort_order = new Array();

if($('#filter-form')){ //if there is a filter form (only on pages with one table)
	$('#filter-form').submit(function(ev){
		ev.preventDefault();
		updatePage(this);
	});
	
	$('#set-filters').click(function(ev){
		ev.preventDefault();
		if($('#set-filters').hasClass('expanded')){
			$('#set-filters').removeClass('expanded');
			$('#filter-form').removeClass('expanded');
		}
		else{
			//var width = $('#set-filters').parent().width();
			$('#set-filters').addClass('expanded');
			$('#filter-form').addClass('expanded');
			//$('#set-filters').parent().width(width);
		}
	})

	$('.download-links').click(function(ev){
		params = encodeURIComponent(JSON.stringify($("#filter-form").serializeObject()));
		ev.target.setAttribute('href', ev.target.getAttribute('href') + '/' + params)
	})
}

(function($) {
	  return $.fn.serializeObject = function() {
	    var json, patterns, push_counters,
	      _this = this;
	    json = {};
	    push_counters = {};
	    patterns = {
	      validate: /^[a-zA-Z][a-zA-Z0-9_]*(?:\[(?:\d*|[a-zA-Z0-9_]+)\])*$/,
	      key: /[a-zA-Z0-9_]+|(?=\[\])/g,
	      push: /^$/,
	      fixed: /^\d+$/,
	      named: /^[a-zA-Z0-9_]+$/
	    };
	    this.build = function(base, key, value) {
	      base[key] = value;
	      return base;
	    };
	    this.push_counter = function(key) {
	      if (push_counters[key] === void 0) {
	        push_counters[key] = 0;
	      }
	      return push_counters[key]++;
	    };
	    $.each($(this).serializeArray(), function(i, elem) {
	      var k, keys, merge, re, reverse_key;
	      if (!patterns.validate.test(elem.name)) {
	        return;
	      }
	      keys = elem.name.match(patterns.key);
	      merge = elem.value;
	      reverse_key = elem.name;
	      while ((k = keys.pop()) !== void 0) {
	        if (patterns.push.test(k)) {
	          re = new RegExp("\\[" + k + "\\]$");
	          reverse_key = reverse_key.replace(re, '');
	          merge = _this.build([], _this.push_counter(reverse_key), merge);
	        } else if (patterns.fixed.test(k)) {
	          merge = _this.build([], k, merge);
	        } else if (patterns.named.test(k)) {
	          merge = _this.build({}, k, merge);
	        }
	      }
	      return json = $.extend(true, json, merge);
	    });
	    return json;
	  };
	})(jQuery);
