from mininet.topo import Topo  
from mininet.node import CPULimitedHost, Host, Node
from mininet.node import OVSSwitch
from mininet.topo import Topo

class VLANHost( Host ):
    def config( self, vlan=100, **params ):
        """Configure VLANHosts """
        r = super( Host, self ).config( **params )
        intf = self.defaultIntf()
        self.cmd( 'ifconfig %s inet 0' % intf )
        self.cmd( 'vconfig add %s %d' % ( intf, vlan ) )
        self.cmd( 'ifconfig %s.%d inet %s' % ( intf, vlan, params['ip'] ) )
        newName = '%s.%d' % ( intf, vlan )
        intf.name = newName
        self.nameToIntf[ newName ] = intf
        return r

class MyTopo( Topo ):  
    "Simple topology example."
    def __init__( self ):
        "Create custom topo."

        # Initialize topology
        Topo.__init__( self )

        # Add hosts and switches TODO
        h1 = self.addHost( 'h1' , mac='00:00:00:00:00:01', ip='192.168.1.1/24', cls=VLANHost, vlan=200)
        h2 = self.addHost( 'h2' , mac='00:00:00:00:00:02', ip='192.168.1.2/24', cls=VLANHost, vlan=300)
        h3 = self.addHost( 'h3' , mac='00:00:00:00:00:03', ip='192.168.1.3/24', cls=VLANHost, vlan=200)
        h4 = self.addHost( 'h4' , mac='00:00:00:00:00:04', ip='192.168.1.4/24', cls=VLANHost, vlan=300)
        SERVER = self.addHost( 'SERVER' , mac='02:00:00:00:20:03',ip='192.168.1.5/24')
        CLIENT = self.addHost( 'CLIENT' , mac='02:00:00:00:20:04',ip='192.168.1.6/24')
        Switch1 = self.addSwitch( 'Switch1' )
        Switch2 = self.addSwitch( 'Switch2' )
        Switch3 = self.addSwitch( 'Switch3' )
        Switch4 = self.addSwitch( 'Switch4' )

        # Add links TODO 
        self.addLink( h1, Switch1 )
        self.addLink( h2, Switch1 )
        self.addLink( h3, Switch1 )
        self.addLink( SERVER, Switch1 )
        self.addLink( Switch1, Switch2 )
        self.addLink( Switch1, Switch3 )
        self.addLink( Switch2, Switch4 )
        self.addLink( Switch3, Switch4 )
        self.addLink( h4, Switch4 )
        self.addLink( CLIENT, Switch4 )
        
topos = { 'mytopo': ( lambda: MyTopo() ) } 
