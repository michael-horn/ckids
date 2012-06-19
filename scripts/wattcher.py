#!/usr/bin/env python
import serial, time, datetime, sys
from xbee import xbee
import sensorhistory
import httplib, urllib

#-------------------------------------------------------------
class Meter:
   
   # cumulative watt hours   
   watthr = 0
   
   # 5 minute timer
   timer = 0
   
   lasttime = 0
   
   
   def __init__(self):
      self.watthr = 0
      self.timer = 0
      self.lasttime = time.time()
      
   def resetTimer(self):
      self.timer = time.time()
      self.watthr = 0

   def addReading(self, watthr):
      self.watthr += float(watthr)
      
   def getAvgWatts(self):
      return self.watthr * (60.0 * 60.0 / (time.time() - self.timer))

                            
SERIALPORT = "/dev/cu.usbserial-FTFB2HQH"
BAUDRATE = 9600      # the baud rate we talk to the xbee
CURRENTSENSE = 4       # which XBee ADC has current draw data
VOLTSENSE = 0          # which XBee ADC has mains voltage data
MAINSVPP = 170 * 2     # +-170V is what 120Vrms ends up being (= 120*2sqrt(2))
vrefcalibration = [492,  # Calibration for sensor #0
                   481,  # Calibration for sensor #1
                   493,  # Calibration for sensor #2
                   465,  # Calibration for sensor #3
                   492,  # Calibration for sensor #4
                   493]  # etc... approx ((2.4v * (10Ko/14.7Ko)) / 3
CURRENTNORM = 15.5  # conversion to amperes from ADC
NUMWATTDATASAMPLES = 1800 # how many samples to watch in the plot window, 1 hr @ 2s samples
DATAURL = "/~msh801/tidal/ckids/instant.php";
DATAHOST = "localhost";


# meter objects
meters = []
for i in range(10):
   meters.append(Meter())


# open up the FTDI serial port to get data transmitted to xbee
ser = serial.Serial(SERIALPORT, BAUDRATE)
ser.open()

            
DEBUG = False
if (sys.argv and len(sys.argv) > 1):
   if sys.argv[1] == "-d":
      DEBUG = True


# the 'main loop' runs once a second or so
def update_graph(idleevent):
   global DEBUG
     
   # grab one packet from the xbee, or timeout
   packet = xbee.find_packet(ser)
   if not packet:
      return        # we timedout
    
   xb = xbee(packet)             # parse the packet
   if DEBUG:       # for debugging sometimes we only want one
      print xb
        
   # we'll only store n-1 samples since the first one is usually messed up
   voltagedata = [-1] * (len(xb.analog_samples) - 1)
   ampdata = [-1] * (len(xb.analog_samples ) -1)
    
   # grab 1 thru n of the ADC readings, referencing the ADC constants
   # and store them in nice little arrays
   for i in range(len(voltagedata)):
      voltagedata[i] = xb.analog_samples[i+1][VOLTSENSE]
      ampdata[i] = xb.analog_samples[i+1][CURRENTSENSE]

   # get max and min voltage and normalize the curve to '0'
   # to make the graph 'AC coupled' / signed
   min_v = 1024     # XBee ADC is 10 bits, so max value is 1023
   max_v = 0
   for i in range(len(voltagedata)):
      if (min_v > voltagedata[i]):
         min_v = voltagedata[i]
      if (max_v < voltagedata[i]):
         max_v = voltagedata[i]

   # figure out the 'average' of the max and min readings
   avgv = (max_v + min_v) / 2
    
   # also calculate the peak to peak measurements
   vpp =  max_v-min_v

   for i in range(len(voltagedata)):
      #remove 'dc bias', which we call the average read
      voltagedata[i] -= avgv
      # We know that the mains voltage is 120Vrms = +-170Vpp
      voltagedata[i] = (voltagedata[i] * MAINSVPP) / vpp

   # normalize current readings to amperes
   for i in range(len(ampdata)):
      # VREF is the hardcoded 'DC bias' value, its
      # about 492 but would be nice if we could somehow
      # get this data once in a while maybe using xbeeAPI
      if vrefcalibration[xb.address_16]:
         ampdata[i] -= vrefcalibration[xb.address_16]
      else:
         ampdata[i] -= vrefcalibration[0]
      # the CURRENTNORM is our normalizing constant
      # that converts the ADC reading to Amperes
      ampdata[i] /= CURRENTNORM

   # calculate instant. watts, by multiplying V*I for each sample point
   wattdata = [0] * len(voltagedata)
   for i in range(len(wattdata)):
      wattdata[i] = voltagedata[i] * ampdata[i]

   # sum up the current drawn over one 1/60hz cycle
   avgamp = 0
   # 16.6 samples per second, one cycle = ~17 samples
   # close enough for govt work :(
   for i in range(17):
      avgamp += abs(ampdata[i])
   avgamp /= 17.0

   # sum up power drawn over one 1/60hz cycle
   avgwatt = 0
   # 16.6 samples per second, one cycle = ~17 samples
   for i in range(17):         
      avgwatt += abs(wattdata[i])
   avgwatt /= 17.0

   # Log results
   meter_id = xb.address_16

   # Print out our most recent measurements
   print "Meter:", str(meter_id)
   print "\tCurrent in amperes: "+str(avgamp)
   print "\tWatts in VA: "+str(avgwatt)

   if (avgamp > 13):
      return            # hmm, bad data

   # Figure out how many watt hours were used since last reading
   meter = meters[meter_id]
   elapsedseconds = time.time() - meter.lasttime
   watthr = (avgwatt * elapsedseconds) / (60.0 * 60.0)  # 60 seconds in 60 minutes = 1 hr
   meter.lasttime = time.time()
   print "\tWatt hours: "+str(watthr)
   meter.addReading(watthr)
   TidalLog(avgwatt, watthr, meter_id, True)

   # determine the minute of the hour (ie 6:42 -> '42')
   currminute = (int(time.time()) / 60) % 10
   if (((time.time() - meter.timer) >= 60.0) and (currminute % 5 == 0)):
      print "Five Minute Log"
      avgwatt = meter.getAvgWatts()
      TidalLog(avgwatt, meter.watthr, meter_id, False)
      meter.resetTimer()
    
      
def TidalLog(awatts, watthr, meter_id, instant):
   params = {
      'tstamp'    : str(datetime.datetime.now()),  #Current time
      'meter_id'  : meter_id,
      'family_id' : 100,  # We can hardcode the family ID for now
      'awatts'    : awatts,  # Average watts
      'watthr'    : watthr,  # Watt hours
      'instant'   : str(instant)
   }
   
   headers = {
      "Content-type": "application/x-www-form-urlencoded",
      "Accept": "text/plain"
      }

   conn = httplib.HTTPConnection(DATAHOST)
   conn.request("POST", "/~msh801/tidal/ckids/instant.php", urllib.urlencode(params), headers)
   response = conn.getresponse()
   print response.status, response.reason
   conn.close()


while True:
   update_graph(None)

