// Update the local timeline from a Orbited server

var OrbitedUpdater = function()
{
     return {

          init: function(server, port, timeline, username, password)
          {
               // set up stomp client.
               stomp = new STOMPClient();

               stomp.onmessageframe = function(frame) {
                    RealtimeUpdate.receive(JSON.parse(frame.body));
               };

               stomp.onconnectedframe = function() {
                    stomp.subscribe(timeline);
               }

               stomp.connect(server, port, username, password);
          }
     }
}();

