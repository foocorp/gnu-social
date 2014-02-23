// Update the local timeline from a Meteor server

var MeteorUpdater = function()
{
     return {

          init: function(server, port, timeline)
          {
               Meteor.callbacks["process"] = function(data) {
                    RealtimeUpdate.receive(JSON.parse(data));
               };

               Meteor.host = server;
               Meteor.port = port;
               Meteor.joinChannel(timeline, 0);
               Meteor.connect();
          }
     }
}();

