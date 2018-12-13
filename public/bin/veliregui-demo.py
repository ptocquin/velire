#!/usr/bin/env python3
# -*- coding: utf-8 -*-

'''
------------------------------------------------------------------------------
CONTRÔLEUR DU SPOT VELIRE
------------------------------------------------------------------------------
Anthony Fratamico
Laboratoire de Physiologie Végétale
Institut de Botanique, Université de Liège
19 octobre 2018
------------------------------------------------------------------------------
'''
import sys
import os
import argparse
from velire import velire
from time import sleep
import pprint
import json

# Version
release = "0.0 (dev, 2018-09-18)"

# Arguments
parser = argparse.ArgumentParser(description='Control VeLiRe Lightings.')
parser.add_argument('-p', type=str, dest='port', required=True, help="Serial port (eg. /dev/ttyUSB0)")
parser.add_argument('-s', type=int, nargs='+', dest='spots', required=True, help='Spot list (eg. 1 2 3 10)')
parser.add_argument('--info', action='store_true', help='Return spots info')
parser.add_argument('--off', action='store_true', help='Shutdown all spots')
parser.add_argument('-c', type=str, nargs='+', dest='color', required=False, help="Color's led to command (eg. WH_4000)")
parser.add_argument('-i', type=int, nargs='+', dest='intensity', required=False, help="Intensity (0-100 %)")
args = vars(parser.parse_args())
#print(args)

# Création du réseau de spots
g = velire.Grid()
g.new(spots_add=args["spots"], port=args["port"])
g.set_freq(50) # Hz
g.open()

# Informations
if args["info"] == True:
	pprint.pprint(g.get_info())
	#print(json.dumps(g.get_info()))

# Off
if args["off"] == True:
	g.shutdown()

# Controle
if args["color"] != None and args["intensity"] != None:
	if len(args["color"]) == len(args["intensity"]):
		for i in range(0, len(args["color"])):
			g.set_bycolor({"colortype": args["color"][i], "intensity" : args["intensity"][i], "unit": "%", "start": 0, "stop": 1})

# Sortie
g.close()
exit()