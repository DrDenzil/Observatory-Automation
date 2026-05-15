Mininet Lab Container
=====================

Docker container for running Mininet networking lab exercises.

Quick Start
-----------

1. Clone the repository:
   From Onedrive link I sent. preferably into /home/$USER/docker
   
2. Run the setup script:
   cd ~/docker/mininet
   bash setup.sh

3. Double-click Mininet-Lab.desktop on your Desktop


Lab Files
---------

File             | Description              | Requirements
-----------------|-------------------------|------------------
Lab04Ex3.py      | SIP Protocol Testing    | None
Lab05_1.py       | VLAN/ONOS               | ONOS SDN controller
Lab05_1_fixed.py | VLAN (standalone)       | None
Lab03_2.py       | WiFi Mobility           | mac80211_hwsim on host


About the Lab Files
-------------------

Lab05_1_fixed.py
This is a fixed version of Lab05_1.py that works without ONOS. 
- The original Lab05_1.py requires an ONOS SDN controller to handle packet forwarding
- The fixed version uses OVS standalone mode, which works out of the box
- Use this version unless you have ONOS set up

Lab03_2.py (WiFi Mobility)
This lab creates a real WiFi network using the mac80211_hwsim kernel module.
- Requires: sudo modprobe mac80211_hwsim on the host before running
- Creates virtual WiFi stations and access points
- Demonstrates mobility/roaming between access points


WiFi Lab Setup
-------------

On the host machine, load the kernel module:
   sudo modprobe mac80211_hwsim

Then run the container - it will detect the WiFi interfaces automatically.
