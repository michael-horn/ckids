const COST_PER_KWH = 0.1;
var curr_watts = 1000;  // current whole-home rate
var rot = 0;  // flower rotation amount

var date = new Date();

var colors = [
   "rgb(121, 173, 210)",
   "rgba(255,85,51,0.8)",
   "rgba(255,119,51,0.8)",
   "rgba(255,153,51,0.8)",
   "rgba(255,187,51,0.8)",
   "rgba(255,221,51,0.8"
];

var xticks = ["12am", "", "2am", "", "4am", "", "6am", "", "8am", "", "10am", "", "noon", "", "2pm", "", "4pm", "", "6pm", "", "8pm", "", "10pm", "", ""];

/* Controls the show / hide meter functionality */
var meterh = [1, 0, 0, 0, 0, 0];

/* Sizing and scales. */
var w = 650;
var h = 400;

//var x = pv.Scale.linear(0, data.length).range(0, w);
var y = pv.Scale.linear(0, 1200).range(0, h);
var yscale = h / 1000;
var xscale = w / (24 * 4);
var yticks = [0, 200, 400, 600, 800, 1000];
var xoff = w; // x-offset used to animate date transitions

var data = [[]];
var vis;

function startup() {
   updateRates();
   setInterval(rotateFlower, 50);
   setInterval(updateRates, 5000);
}

function baseline(d, meter) {
   var sum = 0;
   for (var i=1; i<meter; i++) {
      sum += d[i] * Math.abs(meterh[i]) * 0.01;
   }
   return sum;
}

function addMeter(meter) {
   return vis.add(pv.Area)
      .data(function() data)
      .bottom(function(d) { return baseline(d, meter) * yscale; })
      .height(function(d) d[meter] * yscale * Math.abs(meterh[meter]) * 0.01)
      .left(function() this.index * xscale + xoff)
      .fillStyle(colors[meter])
      .anchor("top").add(pv.Line)
      .strokeStyle(colors[meter])
      .lineWidth(1);
}

function createVis() {
   vis = new pv.Panel()
      .width(w)
      .height(h)
      .bottom(20)
      .left(30)
      .right(10)
      .top(5);
      
   for (var i=0; i<=5; i++) addMeter(i);
      
   /* x-axis ticks. */
   vis.add(pv.Rule)
      .data(xticks)
      .left(function() this.index * (w / 24) + xoff)
      .bottom(-5)
      .height(5)
      .anchor("bottom").add(pv.Label);
   
   /* Y-axis and ticks. */
   vis.add(pv.Rule)
      .data(function() yticks)
      .bottom(function(d) d * yscale)
      .strokeStyle(function(d) d ? "rgba(200, 200, 200, 0.5)" : "#000")
      .anchor("left").add(pv.Label)
      .text(function(d) numberWithCommas(d));

   vis.render();
}


function toggleMeter(btn, meter) {
   btn.className = (btn.className == 'meterbtn_on')? 'meterbtn_off' : 'meterbtn_on';

   if (meter >= 0 && meter < meterh.length) {
      var m = meterh[meter];

      if (m != 0 && m < 100) {
         meterh[meter] *= -1;
      } else {
         meterh[meter] = (m == 0) ? 10 : -90;
      }
      animateMeters();
   }
}


function animateMeters() {
   vis.render();
   var changed = false;
   for (var i=0; i<meterh.length; i++) {
      var m = meterh[i];
      if (m != 0 && m < 100) {
         var delta = (m < 0)? -m : 100 - m;
         if (delta <= 1 && m < 0) {
            meterh[i] = 0;
         } else if (delta <= 1 && m > 0) {
            meterh[i] = 100;
         } else {
            meterh[i] += Math.round(delta * 0.4) + 1;
         }
         changed = true;
      }
   }
   if (xoff != 0) {
      xoff *= 0.6;
      if (Math.abs(xoff) < 1) xoff = 0;
      changed = true;
   }
   if (changed) {
      setTimeout(animateMeters, 30);
   }
}


function numberWithCommas(x) {
   var parts = x.toString().split(".");
   parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
   return parts.join(".");
}


function updateRates() {
   var xmlhttp = new XMLHttpRequest();
   xmlhttp.onreadystatechange = function() {
      if (xmlhttp.readyState==4 && xmlhttp.status==200) {
         
         info = eval(xmlhttp.responseText);
         curr_watts = parseInt(info.watts);
         
         document.getElementById("currwatts").innerHTML = info.curr_watts;
         document.getElementById("currcost").innerHTML = "$" + info.curr_cost;
         document.getElementById("daykwh").innerHTML = info.day_kwh;
         document.getElementById("daycost").innerHTML = "$" + info.day_cost;
         document.getElementById("weekkwh").innerHTML = info.week_kwh;
         document.getElementById("weekcost").innerHTML = "$" + info.week_cost;
         document.getElementById("monthkwh").innerHTML = info.month_kwh;
         document.getElementById("monthcost").innerHTML = "$" + info.month_cost;
      }
   }
   xmlhttp.open("GET", "curr_rate.php", true);
   xmlhttp.send();
}


function updateData(date) {
   var xmlhttp = new XMLHttpRequest();
   xmlhttp.onreadystatechange = function() {
      if (xmlhttp.readyState==4 && xmlhttp.status==200) {
         var json = eval("(" + xmlhttp.responseText + ")");
         data = json.data;
         yscale = h / json.ymax;
         yticks = json.yticks;
         animateMeters();
      }
   }
   var dt = date.toISOString().substring(0, 10);
   xmlhttp.open("GET", "data.php?date=" + dt + "&type=day", true);
   xmlhttp.send();
}

function changeScreen() {
   document.getElementById("currdate").innerHTML = date.toDateString();
   updateData(date);
   return false;
}


function nextScreen() {
   date.setDate(date.getDate() + 1);
   xoff = w;
   return changeScreen();
}

function prevScreen() {
   date.setDate(date.getDate() - 1);
   xoff = -w;
   return changeScreen();
}

function todayScreen() {
   date = new Date();
   return changeScreen();
}


function rotateFlower() {
   var img = document.getElementById("flower");
   if (img) {
      img.style.transform = "rotate(" + rot + "deg)";
      img.style.MozTransform = "rotate(" + rot + "deg)";
      img.style.WebkitTransform = "rotate(" + rot + "deg)";
      rot += (curr_watts / 200);
      if (rot >= 360) rot -= 360;
   }
}
