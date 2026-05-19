var center=[50.98629,4.514818];
var densityRadiusMeters=50;
var highDensityCount=4;
var map=L.map('map').setView(center,17);
var events=[];
var markers=L.layerGroup().addTo(map);
var heat=null;
var showHeat=true;
var showDots=true;

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:20,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);

function esc(value){return String(value||'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')}

function distanceMeters(a,b){
    var earthRadius=6371000;
    var dLat=(Number(b.latitude)-Number(a.latitude))*Math.PI/180;
    var dLng=(Number(b.longitude)-Number(a.longitude))*Math.PI/180;
    var lat1=Number(a.latitude)*Math.PI/180;
    var lat2=Number(b.latitude)*Math.PI/180;
    var x=Math.sin(dLat/2)*Math.sin(dLat/2)+Math.cos(lat1)*Math.cos(lat2)*Math.sin(dLng/2)*Math.sin(dLng/2);
    return 2*earthRadius*Math.atan2(Math.sqrt(x),Math.sqrt(1-x));
}

function densityCount(event){
    var count=0;
    events.forEach(function(other){
        if(distanceMeters(event,other)<=densityRadiusMeters){count++;}
    });
    return count;
}

function heatPoint(event){
    var density=densityCount(event);
    var intensity=Math.max(0.25,Math.min(1,density/highDensityCount));
    return [Number(event.latitude),Number(event.longitude),intensity];
}

function render(){
    markers.clearLayers();

    if(heat){map.removeLayer(heat);heat=null;}

    if(showHeat&&events.length){
        heat=L.heatLayer(events.map(heatPoint),{
            radius:44,
            blur:22,
            maxZoom:20,
            minOpacity:0.35,
            gradient:{0.25:'#00b050',0.5:'#ffff00',0.75:'#ff9900',1:'#ff0000'}
        }).addTo(map);
    }

    if(showDots){
        events.forEach(function(event){
            var density=densityCount(event);
            L.circleMarker([Number(event.latitude),Number(event.longitude)],{
                radius:7,
                weight:2,
                color:'#1f4867',
                fillColor:'#c83a2e',
                fillOpacity:0.9
            }).bindPopup('<strong>'+esc(event.name)+'</strong><br>'+Number(event.latitude).toFixed(6)+', '+Number(event.longitude).toFixed(6)+'<br>Density: '+density+' event'+(density===1?'':'s')+' within '+densityRadiusMeters+'m').addTo(markers);
        });
    }

    eventCount.textContent=events.length+' event'+(events.length===1?'':'s')+' saved';
    eventList.innerHTML=events.slice().reverse().map(function(event){
        var density=densityCount(event);
        return '<li><strong>'+esc(event.name)+'</strong><br><span>'+Number(event.latitude).toFixed(6)+', '+Number(event.longitude).toFixed(6)+' · density '+density+'/'+highDensityCount+' within '+densityRadiusMeters+'m</span></li>';
    }).join('');
}

async function load(){
    events=await(await fetch('/api/events',{cache:'no-store'})).json();
    render();
    if(events.length){
        map.fitBounds(L.latLngBounds(events.map(function(event){return [Number(event.latitude),Number(event.longitude)]})).pad(0.25),{maxZoom:18});
    }
}

map.on('click',function(event){
    eventLat.value=event.latlng.lat.toFixed(6);
    eventLng.value=event.latlng.lng.toFixed(6);
    selectedHint.textContent='Selected: '+eventLat.value+', '+eventLng.value;
});

toggleHeatmap.onchange=function(){showHeat=toggleHeatmap.checked;render();};
toggleDots.onchange=function(){showDots=toggleDots.checked;render();};
printMapButton.onclick=function(){map.invalidateSize();setTimeout(function(){window.print();},150);};

eventForm.onsubmit=async function(event){
    event.preventDefault();
    var payload={name:eventName.value.trim(),latitude:Number(eventLat.value),longitude:Number(eventLng.value),weight:Number(eventWeight.value||1)};
    if(!payload.name){return;}
    events=await(await fetch('/api/events',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})).json();
    eventName.value='';
    eventWeight.value=1;
    render();
    map.flyTo([payload.latitude,payload.longitude],18);
};

clearButton.onclick=async function(){
    if(!confirm('Clear all events?')){return;}
    events=await(await fetch('/api/events',{method:'DELETE'})).json();
    render();
};

load();
