#!/usr/bin/env python3
"""
LX200GPS Initialization Script

Sends :I# (Initialize Telescope) command to LX200GPS via serial port.
This is needed because LX200GPS (Autostar II) cannot be unparked by INDI -
the telescope must be power-cycled or initialized to respond after parking.

Usage:
    python3 lx200gps_init.py [--port /dev/ttyUSB0] [--baud 9600]

Can be run standalone or integrated with INDI/EKOS workflows.
"""

import argparse
import sys
import time
import logging
from pathlib import Path

try:
    import serial
except ImportError:
    print("Error: pyserial not installed. Run: pip install pyserial")
    sys.exit(1)

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

DEFAULT_PORT = '/dev/ttyUSB0'
DEFAULT_BAUD = 9600
LX200_TIMEOUT = 5


def send_command(ser, command, wait_response=True):
    """Send a command to LX200GPS and optionally read response."""
    try:
        ser.write(command.encode())
        if wait_response:
            time.sleep(0.5)
            response = ser.read(ser.in_waiting).decode('ascii', errors='ignore')
            return response.strip()
    except Exception as e:
        logger.error(f"Communication error: {e}")
        return None
    return ""


def initialize_telescope(port=DEFAULT_PORT, baud=DEFAULT_BAUD, retries=3):
    """
    Initialize LX200GPS telescope via serial.
    
    The :I# command tells the telescope to re-initialize its position
    and respond to commands again after being parked or unresponsive.
    
    Returns: True if initialization succeeded, False otherwise
    """
    for attempt in range(1, retries + 1):
        logger.info(f"Attempt {attempt}/{retries}: Connecting to {port} at {baud} baud")
        
        try:
            with serial.Serial(port, baud, timeout=LX200_TIMEOUT) as ser:
                ser.flushInput()
                ser.flushOutput()
                
                logger.info("Sending :I# (Initialize Telescope)...")
                response = send_command(ser, ':I#')
                
                if response is not None:
                    logger.info(f"Telescope initialized. Response: '{response}'")
                    return True
                else:
                    logger.warning(f"No response from telescope (attempt {attempt})")
                    
        except serial.SerialException as e:
            logger.error(f"Serial error: {e}")
        except Exception as e:
            logger.error(f"Unexpected error: {e}")
        
        if attempt < retries:
            logger.info("Waiting 2 seconds before retry...")
            time.sleep(2)
    
    logger.error("Failed to initialize telescope after all retries")
    return False


def check_telescope_responsive(port=DEFAULT_PORT, baud=DEFAULT_BAUD):
    """Check if telescope responds to a simple command."""
    try:
        with serial.Serial(port, baud, timeout=LX200_TIMEOUT) as ser:
            ser.flushInput()
            
            logger.info("Checking telescope responsiveness with :GW#...")
            response = send_command(ser, ':GW#')
            
            if response and response != '':
                logger.info(f"Telescope responsive. GPS status: {response}")
                return True
            else:
                logger.warning("No response from telescope")
                return False
                
    except Exception as e:
        logger.error(f"Failed to check telescope: {e}")
        return False


def main():
    parser = argparse.ArgumentParser(description='Initialize LX200GPS telescope via serial')
    parser.add_argument('--port', default=DEFAULT_PORT, help='Serial port device')
    parser.add_argument('--baud', type=int, default=DEFAULT_BAUD, help='Baud rate')
    parser.add_argument('--check', action='store_true', help='Only check responsiveness, do not initialize')
    parser.add_argument('--verbose', '-v', action='store_true', help='Verbose output')
    
    args = parser.parse_args()
    
    if args.verbose:
        logger.setLevel(logging.DEBUG)
    
    if args.check:
        success = check_telescope_responsive(args.port, args.baud)
    else:
        logger.info(f"LX200GPS Initialization Script")
        logger.info(f"Port: {args.port}, Baud: {args.baud}")
        success = initialize_telescope(args.port, args.baud)
    
    return 0 if success else 1


if __name__ == '__main__':
    sys.exit(main())
