function Magor() {}
var magor = new Magor();

(function(magor) {

  // copied from http://stackoverflow.com/a/901144/564274
  // We don't need the server to parse the query string parameters that doesn't
  // need to be processed at the server-side. And many of the params are only
  // useful at the client-side.
  magor.getParameterByName = function(name) {
    name = name.replace(/([\[\]])/g, "\\$1");
    //name = name.replace(/[\[]/, "\\\[").replace(/[\]]/, "\\\]");
    var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
        results = regex.exec(location.search);
    return results == null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
  }

  magor.capitalizeName = function(name) {
    var words = name.toLowerCase().split(/[_. ]/);
    for (var i = 0; i < words.length; i++) {
      words[i] = words[i].charAt(0).toUpperCase() + words[i].slice(1);
    }
    return words.join(' ');
  }

})(magor);
