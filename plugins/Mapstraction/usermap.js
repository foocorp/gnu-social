function scrapeNotices(user)
{
     var notices = [];
     $(".notice").each(function(){
          var notice = getNoticeFromElement($(this));
          if (user) {
               notice['user'] = user;
          } else {
               notice['user'] = getUserFromElement($(this));
          }
          if(notice['geo'])
               notices.push(notice);
     });

     return notices;
}

function scrapeUser()
{
     var avatarURL = $(".entity_profile .entity_depiction img.avatar").attr('src');
     var profileURL = $(".entity_profile .entity_nickname .url").attr('href');
     var nickname = $(".entity_profile .entity_nickname .nickname").text();

     return {
        'profile_image_url': avatarURL,
        'profile_url': profileURL,
        'screen_name': nickname
     };
}

function getNoticeFromElement(noticeElement)
{
    var notice = {};

    if(noticeElement.find(".geo").length) {
        var latlon = noticeElement.find(".geo").attr('title').split(";");
        notice['geo']={'coordinates': [
            parseFloat(latlon[0]),
            parseFloat(latlon[1])] };
    }

    notice['html'] = noticeElement.find(".entry-content").html();
    notice['url'] = noticeElement.find("a.timestamp").attr('href');
    notice['created_at'] = noticeElement.find("abbr.published").text();

    return notice;
}

function getUserFromElement(noticeElement)
{
     var avatarURL = noticeElement.find("img.avatar").attr('src');
     var profileURL = noticeElement.find(".author a.url").attr('href');
     var nickname =  noticeElement.find(".author .nickname").text();

     return {
          'profile_image_url': avatarURL,
          'profile_url': profileURL,
          'screen_name': nickname
    };
}

function showMapstraction(element, notices) {
     if(element instanceof jQuery) element = element[0];
     if(! $.isArray(notices)) notices = [notices];
     var mapstraction = new mxn.Mapstraction(element, _provider);

     var minLat = 181.0;
     var maxLat = -181.0;
     var minLon = 181.0;
     var maxLon = -181.0;

     for (var i in notices)
     {
          var n = notices[i];

          var lat = n['geo']['coordinates'][0];
          var lon = n['geo']['coordinates'][1];

          if (lat < minLat) {
               minLat = lat;
          }

          if (lat > maxLat) {
               maxLat = lat;
          }

          if (lon < minLon) {
               minLon = lon;
          }

          if (lon > maxLon) {
               maxLon = lon;
          }

          pt = new mxn.LatLonPoint(lat, lon);
          mkr = new mxn.Marker(pt);

          mkr.setIcon(n['user']['profile_image_url'], [24, 24]);
          mkr.setInfoBubble('<a href="'+ n['user']['profile_url'] + '">' + n['user']['screen_name'] + '</a>' + ' ' + n['html'] +
                            '<br/><a href="'+ n['url'] + '">'+ n['created_at'] + '</a>');

          mapstraction.addMarker(mkr);
     }

     bounds = new mxn.BoundingBox(minLat, minLon, maxLat, maxLon);

     mapstraction.setBounds(bounds);
}
