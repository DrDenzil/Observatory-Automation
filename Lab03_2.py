#!/usr/bin/python
import sys
from mininet.node import Controller
from mininet.log import setLogLevel, info
from mn_wifi.cli import CLI as CLI_wifi
from mn_wifi.net import Mininet_wifi

def topology(plot):
    "Create a network."
    net = Mininet_wifi(controller=Controller)

    info("*** Creating nodes\n")
    #TODO: Task1
    sta1 = net.addStation('sta1', mac='00:00:00:00:00:02', ip='10.0.0.2/8', position ='10,30,0', range=20, min_v=20, max_v=20) 
    sta2 = net.addStation('sta2', mac='00:00:00:00:00:03', ip='10.0.0.3/8', position='210,30,0', range=20, min_v=10, max_v=10)     
    ap1 = net.addAccessPoint('ap1', mac='00:00:00:00:10:02', ssid='ssid-ap1', mode='b', channel='1', failMode="standalone",position='50,50,0', range=100)
    ap2 = net.addAccessPoint('ap2', mac='00:00:00:00:10:03', ssid='ssid-ap2', mode='b', channel='6',failMode="standalone",position='250,50,0', range=100)
    c1 = net.addController('c1')
    net.setPropagationModel(model="logDistance", exp=5)
    info("*** Configuring wifi nodes\n")
    net.configureWifiNodes()
    info("*** Creating links\n")
    #TODO: Task2
    net.plotGraph(min_x=-100, min_y=-100, max_x=500, max_y=700)
    net.addLink(ap1,ap2)
    #TODO: Task3
    net.startMobility(time=0)
    net.mobility(sta1,'start', time=1, position='10,30,0')
    net.mobility(sta2,'start', time=1, position='210,30,0')
    net.mobility(sta1,'stop', time=11, position='210,30,0')
    net.mobility(sta2,'stop', time=11, position='10,30,0')
    net.stopMobility(time=12)
    c1.start()
    #TODO: Task4
    
    info("*** Starting network\n")
    net.build()
    info("*** Running CLI\n")
    CLI_wifi(net)
    info("*** Stopping network\n")
    net.stop()

if __name__ == '__main__':
    setLogLevel('info')
    plot = False if '-p' in sys.argv else True
    topology(plot)
 

