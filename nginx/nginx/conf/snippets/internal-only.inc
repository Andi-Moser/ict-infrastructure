## Internal-only access -----------------------------------------------------------------------------
# Include this inside a "server" block to block all requests from outside our network.
# This file must NOT end in .conf: the whole conf directory is mounted as conf.d and every *.conf
# there is loaded automatically at http level.

allow 213.144.139.58;   # ICT-BZ public IP (internal clients via NAT)
deny all;               # Deny all other IPs
