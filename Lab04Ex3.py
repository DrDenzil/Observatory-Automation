from mininet.topo import Topo
from mininet.net import Mininet
from mininet.node import Node
from mininet.log import setLogLevel, info
from mininet.cli import CLI
  
class MyTopo( Topo ):  
    "Simple topology example."
    def __init__( self ):
        "Create custom topo."

        # Initialize topology
        Topo.__init__( self )

        # Add hosts and switches TODO
        Host1 = self.addHost( 'SIPserver' )
        Host2 = self.addHost( 'h2' )
        Host3 = self.addHost( 'h3' )
        Host4 = self.addHost( 'SIPclient' )
        Switch1 = self.addSwitch('s1')
        Switch2 = self.addSwitch('s2')
        Switch3 = self.addSwitch('s3')
        
        # Add links TODO 
        self.addLink( Switch1, Switch2 )
        self.addLink( Switch1, Switch3 )
        self.addLink( Host3, Switch3 )
        self.addLink( Host4, Switch3 )
        self.addLink( Host1, Switch2 )
        self.addLink( Host2, Switch2 )
         
topos = { 'mytopo': ( lambda: MyTopo() ) } 
