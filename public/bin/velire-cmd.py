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
# -----------------------------------------------------------------------------
# Librairies
import sys
import os
import argparse
from velire import velire
from time import sleep
import pprint
import json
# -----------------------------------------------------------------------------
# Version
release = "0.0 (dev, 2018-09-18)"
# -----------------------------------------------------------------------------
# Dictionnaire des erreurs
errors_dict = {
0: {"msg": "", "doc": "Aucune erreur rencontrée"},
1: {"msg": "Unknown error occurs", "doc": "Erreur non documentée"},
11:{"msg": "Inconsistent length of arguments"},
21:{"msg": "Setting channels failed", "doc": "La modification de la configuration d'un canal n'a pas été prise en compte par le spot"}
}
errors = []
# -----------------------------------------------------------------------------
# Arguments
parser = argparse.ArgumentParser(description='Control VeLiRe Lightings.')
parser.add_argument('-p', '--port', type=str, dest='port', required=True, help="Serial port (eg. /dev/ttyUSB0)")
parser.add_argument('-s', '--spot', type=str, nargs='+', dest='spots', required=True, help='Spot list (eg. 1 2 3-10 22)')

parser.add_argument('--init', action='store_true', help='Define function master/slave')
parser.add_argument('--test', action='store_true', dest='test', help='Test if spots are connected')

parser.add_argument('-c', '--color', type=str, nargs='+', dest='color', required=False, help="Color's led to command (eg. WH_4000)")
parser.add_argument('-i', '--intensity', type=int, nargs='+', dest='intensity', required=False, help="Intensity (0-100)")
parser.add_argument('-e', '--exclusive', action='store_true', help='Shutdown unmentioned colors')

parser.add_argument('--off', action='store_true', help='Turn off all spots')
parser.add_argument('--on', action='store_true', help='Turn on all spots')
parser.add_argument('--shutdown', action='store_true', help='Shutdown all channels')

parser.add_argument('--info', type=str, nargs='+', dest='info', required=False, help='Return desired spots info')
parser.add_argument('--output', type=str, nargs=1, required=False, dest="out_file", help='Destination file for info data (ignored if --info is not set to all)')
parser.add_argument('--input', type=str, nargs=1, dest='conf_file', required=False, help="File containing grid settings (as returning by '... --info all --output-file *.json' command) used for faster controls")

parser.add_argument('--json', action='store_true', dest='json', help='Print output as json')
parser.add_argument('-q', '--quiet', action='store_true', dest='quiet', help='No print during execussion')
parser.add_argument('--demo', type=str, nargs='+', required=False, dest="demo", help='Demo')
parser.add_argument('-v', '--version', action='store_true', dest='version', help='Print version')
args = vars(parser.parse_args())
# -----------------------------------------------------------------------------
# Fonction verbose
quiet = args["quiet"]
def verbose(msg):
	global quiet
	if quiet == False:
		print(msg)
if quiet == True:
	velire.verbosity = 1
else:
	velire.verbosity = 5
# -----------------------------------------------------------------------------
# Création du réseau de spots (non chargés)
verbose("Initialization")
g = velire.Grid()
# -----------------------------------------------------------------------------
# Liste des spots
spots_ad_list = []
for ad in args["spots"]:
	try:
		spots_ad_list.append(int(ad))
	except:
		try:
			spots_ad_list.extend(list(range(int(ad.split('-')[0]), int(ad.split('-')[1])+1)))
		except:
			verbose("... Unvalid spot address "+str(ad))
if len(spots_ad_list) < 0:
	verbose("No valid spot address found\nEXIT")
	sys.exit()
verbose("... adding spots "+str(spots_ad_list))
g.new(spots_add=spots_ad_list, port=args["port"])
g.open()
verbose("... done")
# -----------------------------------------------------------------------------
# Recherche des spots sur le réseau
if args["test"] == True:
	verbose("Searching spot on grid")
	reply = g.find_spot()
	verbose("... "+str(len(reply["found"]))+" spot(s) found")
	verbose("... "+str(len(reply["not_found"]))+" spot(s) NOT found")
	if args["json"] == True:
		print(json.dumps(reply))
	else:
		pprint.pprint(reply)
	verbose("... done")
	sys.exit()
# -----------------------------------------------------------------------------
# Défini le rôle de master/slave
if args["init"] == True:
	verbose("Setting master/slave")
	verbose("... searching spots")
	reply = g.find_spot()
	verbose("... ... "+str(len(reply["found"]))+" spot(s) found")
	verbose("... ... "+str(len(reply["not_found"]))+" spot(s) NOT found")
	verbose("... setting function")
	for s in g.spots_list:
		if int(s.address) in reply['found']:
			if int(s.address) == int(min(reply['found'])):
				r = s.set_ms(master=True)
				verbose("... ... spot at "+str(s.address)+" defined as MASTER")
			else:
				r = s.set_ms(master=False) # tous esclaves
				verbose("... ... spot at "+str(s.address)+" defined as slave")
		else:
			verbose("... ... spot at "+str(s.address)+" not found")
	verbose("... done")
# -----------------------------------------------------------------------------
# Chargement de la configuration des spots: via un fichier ou depuis la mémoire des luminaires
if args["conf_file"] != None:
	verbose("Loading configuration from file "+args["conf_file"][0])
	with open(args["conf_file"][0]) as f:
		data = json.load(f)
	g.activate2(data)
	verbose("... done")
else:
	verbose("Loading configuration from hardware")
	g.activate() # depuis les infos des luminaires
	verbose("... done")
# -----------------------------------------------------------------------------
# Informations
if args["info"] != None:
	if "all" in args["info"]:
		verbose("Loading configuration from hardware")
		g.activate()
		infos = g.get_info()
		if args["out_file"] != None:
			with open(args["out_file"][0], 'w') as f: # Sauvegarde de la configuration dans un fichier
				json.dump(infos, f)
		else:
			if args['json'] == True:
				print(json.dumps(infos))
			else:
				pprint.pprint(infos)
# -----------------------------------------------------------------------------
# Off / On / Shutdown
if args["on"] == True:
	verbose("Turning on")
	g.set_state(1)
	verbose("... done")

if args["off"] == True:
	verbose("Turning off")
	g.set_state(0)
	verbose("... done")

if args["shutdown"] == True:
	verbose("Shutting down")
	g.shutdown()
	verbose("... done")
# -----------------------------------------------------------------------------
# Controle de l'intensité des canaux
if args["color"] != None and args["intensity"] != None:
	verbose("Color settings")
	g.set_state(1) # allume les spots
	if len(args["color"]) == len(args["intensity"]): # vérification de la ligne de commande reçue
		for c in g.available_colors:
			if c in args["color"]:
				i = args["color"].index(c) #index de la couleur dans la liste pour retrouver l'intensité correspondante
				reply = g.set_bycolor({"colortype": c, "intensity" : 0.5*args["intensity"][i], "unit": "%", "start": 0, "stop": 1}) # 0.5*intensité car courrant continu > bridé à 50%
			else:
				if args['exclusive'] == True:
					reply = g.set_bycolor({"colortype": c, "intensity" : 0, "unit": "%", "start": 0, "stop": 1})
		unavailable_colors = list(set(args["color"]) - set(g.available_colors))
		if len(unavailable_colors) > 0:
			verbose("WARNING: color(s) "+str(unavailable_colors)+" not available")
	else:
		verbose("ERROR: '--color' and '--intensity' must have the same length")
# -----------------------------------------------------------------------------
# Démo
def demoUpDown(grid):
	values = [1,7,15,20,30,42,30,20,15,7,1]
	for v in values:
		for c in grid.available_colors:
			if c != "UV_280":
				reply = grid.set_bycolor({"colortype": c, "intensity" : v, "unit": "%", "start": 0, "stop": 1})
		verbose("... all channels at "+str(v))
		if v == max(values):
			sleep(5)

def demoSeqColors(grid, time_sleep):
	while True:
		for c in grid.available_colors:
			if c != "UV_280":
				verbose("... color "+str(c))
				reply = grid.set_bycolor({"colortype": c, "intensity" : 40, "unit": "%", "start": 0, "stop": 1})
				sleep(time_sleep)
				reply = grid.set_bycolor({"colortype": c, "intensity" : 0, "unit": "%", "start": 0, "stop": 1})

if args["demo"] != None:
	if args["demo"][0] == "pulse":
		verbose("Pulse demo")
		g.shutdown()
		try:
			while True:
				demoUpDown(g)
		except KeyboardInterrupt:
			verbose("\n... shutting down")
			g.shutdown()
			verbose("... done")

	if args["demo"][0] == "seq":
		verbose("Seq demo")
		try :
			t = int(args["demo"][1])
		except:
			t = 0
		verbose("... start (sleep = "+str(t)+"s)")
		g.shutdown()
		try:
			while True:
				demoSeqColors(g, t)
		except KeyboardInterrupt:
			verbose("\n... shutting down")
			g.shutdown()
			verbose("... done")
# -----------------------------------------------------------------------------
# Sortie
g.close()
#sys.exit(min(errors))
sys.exit()