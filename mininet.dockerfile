# Use python:3.9 as base
FROM python:3.9

# Create work directory
WORKDIR /tmp/mininet-wifi

# Clone mininet-wifi repo
RUN git clone https://github.com/intrig-unicamp/mininet-wifi.git .

# Install dependencies for mininet wifi
RUN apt update 
RUN apt install -y sudo git make help2man pyflakes3 python3-pycodestyle tcpdump wpan-tools inetutils-ping
RUN pip install six numpy matplotlib beautifulsoup4

# Fix install script outdated dependencies names
RUN sed -i 's/pyflakes//g' util/install.sh && sed -i 's/pep8//g' util/install.sh

# Run the installation script
RUN util/install.sh -Wlnfv6 

# Fix matplotlib compatibility: newer matplotlib requires sequences in set_data()
# Patches all instances in mininet-wifi that pass scalar x,y to set_data()
RUN sed -i 's/\.plt_node\.set_data(x, y)/.plt_node.set_data([x], [y])/' \
    /usr/local/lib/python3.9/site-packages/mininet_wifi-2.7-py3.9.egg/mn_wifi/node.py \
    /usr/local/lib/python3.9/site-packages/mininet_wifi-2.7-py3.9.egg/mn_wifi/plot.py

# MODIFICATIONS FROM BASE IMAGE:
# 1. Add vlan package (for Lab05 VLAN functionality)
# 2. Add openvswitch-switch (for OVS switching)
RUN apt-get update && apt-get install -y vlan openvswitch-switch xterm sip-tester vlc

# Allow VLC to run as root in container (it normally refuses via geteuid check)
RUN sed -i 's/geteuid/getppid/g' /usr/bin/vlc 2>/dev/null; \
    sed -i 's/geteuid/getppid/g' /usr/lib/vlc/vlc-wrapper 2>/dev/null; true

# Create app data directory and copy lab files
RUN mkdir -p /AppData/mininet
COPY --chown=root:root Lab*.py /AppData/mininet/
WORKDIR /AppData/mininet
RUN chmod +x Lab*.py 2>/dev/null || true

# Startup script (checks for mac80211_hwsim, starts OVS)
RUN echo '#!/bin/bash\n\
# Check if mac80211_hwsim is loaded on host\n\
if [ ! -d "/sys/kernel/debug/ieee80211" ]; then\n\
    echo "NOTE: mac80211_hwsim not available (required only for Lab03)"\n\
fi\n\
# Fix hostname resolution (needed by sip-tester/SIPp)\n\
HOSTNAME=$(hostname)\n\
IP=$(ip route get 1 2>/dev/null | sed -n "s/.* src \([0-9.]*\).*/\\1/p")\n\
if [ -z "$IP" ]; then\n\
    IP=$(hostname -I 2>/dev/null | awk "{print \$1}")\n\
fi\n\
if [ -n "$IP" ] && ! grep -q "$HOSTNAME" /etc/hosts 2>/dev/null; then\n\
    echo "$IP $HOSTNAME" >> /etc/hosts\n\
fi\n\
# Start OVS (handles both native Linux and WSL2/Windows)\n\
if lsmod 2>/dev/null | grep -q openvswitch; then\n\
    service openvswitch-switch start 2>/dev/null || true\n\
else\n\
    # WSL2 fallback - start OVS userspace daemons\n\
    mkdir -p /var/run/openvswitch\n\
    mkdir -p /etc/openvswitch\n\
    if [ ! -f /etc/openvswitch/conf.db ]; then\n\
        ovsdb-tool create /etc/openvswitch/conf.db 2>/dev/null || true\n\
    fi\n\
    ovsdb-server /etc/openvswitch/conf.db \\\n\
        --remote=punix:/var/run/openvswitch/db.sock \\\n\
        --pidfile --detach 2>/dev/null || true\n\
    ovs-vsctl --no-wait init 2>/dev/null || true\n\
    ovs-vswitchd --pidfile --detach 2>/dev/null || true\n\
fi\n\
# Keep container running\n\
tail -f /dev/null' > /start.sh && chmod +x /start.sh

CMD ["/bin/bash", "/start.sh"]