// update the local timeline from a Comet server
var CometUpdate = function()
{
     var _server;
     var _timeline;
     var _userid;
     var _replyurl;
     var _favorurl;
     var _deleteurl;
     var _cometd;

     return {
          init: function(server, timeline, userid, replyurl, favorurl, deleteurl)
          {
               _cometd = $.cometd; // Uses the default Comet object
               _cometd.init(server);
               _server = server;
               _timeline = timeline;
               _userid = userid;
               _favorurl = favorurl;
               _replyurl = replyurl;
               _deleteurl = deleteurl;
               _cometd.subscribe(timeline, function(message) { RealtimeUpdate.receive(message.data) });
               $(window).unload(function() { _cometd.disconnect(); } );
          }
     }
}();
