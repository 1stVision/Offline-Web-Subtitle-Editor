(function(magor) {

  TAGS = {
    'speaker': {
        'a': 'Anchor',
        'r': 'Reporter',
        'm': 'Male',
        'f': 'Female',
        'u': 'Unknown',
    },
    'environment': {
        's': 'Speech',
        'b': 'Babble',
        'o': 'Outdoor',
        'm': 'Music',
        'h': 'Reverb.',
        't': 'Telephone'
    }
  }

  // replace all the tags with the correct labels
  $(document).ready(function() {
    $('.filter-value + span').each(function() {
      var tag = $(this).text();
      var type = $(this).closest('.filter').attr('filtertype');
      if (type in TAGS && tag in TAGS[type])
        $(this).text(TAGS[type][tag]);
      else if (type == 'speaker') {
        $(this).text(magor.capitalizeName(tag));
      }
    });
  });

  magor.addFilter = function(name, value) {
    var params = location.search.split('&');
    var found = false;
    for (var i = 0; i < params.length; i++) {
      var keyvalue = params[i].split('=');
      var key = keyvalue[0];
      key = key[0] == '?' ? key.slice(1) : key;
      var val = keyvalue[1];
      if (key === name) {
        if (val) val += '+';
        val += value;
        found = true;
      }
      else if (key === 'page') {
        val = 1
      }
      params[i] = key + '=' + val;
    }
    if (!found) {
      params.push(name + '=' + value);
    }
    document.location = location.protocol + '//' + location.host
                      + location.pathname + '?' + params.join('&');
  };

  magor.removeFilter = function(name, value) {
    var params = location.search.split('&');
    var found = false;
    for (var i = 0; i < params.length; i++) {
      var keyvalue = params[i].split('=');
      var key = keyvalue[0];
      key = key[0] == '?' ? key.slice(1) : key;
      var val = keyvalue[1];
      if (key === name) {
        var vals = val.split('+');
        var index = vals.indexOf(escape(value));
        if (index >= 0) {
          vals.splice(index, 1);
        }
        else {
          console.log('error: not found value ' + value + 
                      ' in the current filter ' + name);
        }
        // we have removed the last value in the filter, now remove the filter
        // completely:
        if (vals.length == 0) {
          params.splice(i, 1);
          i--;
          continue;
        }
        else {
          val = vals.join('+');
        }
        found = true;
      }
      else if (key === 'page') {
        val = 1
      }
      params[i] = key + '=' + val;
    }
    if (!found) {
      console.log('error: not found value ' + value + 
                  ' in the current filter ' + name);
    }
    document.location = location.protocol + '//' + location.host
                      + location.pathname + '?' + params.join('&');
  };

})(magor);
