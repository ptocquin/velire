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
from datetime import datetime
from datetime import timedelta
import pprint
import json
import yaml
import sqlite3
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
parser.add_argument('-p', '--port', type=str, dest='port', required=False, help="Serial port (eg. /dev/ttyUSB0). Erased by --config")
parser.add_argument('-s', '--spot', type=str, nargs='+', dest='spots', required=False, help='Spot list (eg. 1 2 3-10 22). Erased by --config')
parser.add_argument('--cluster', type=str, nargs='+', dest='cluster', required=False, help='Clusters list (eg. 1 2 10). Need --config')
parser.add_argument('--set-run', type=str, nargs=1, dest='set_run', required=False, help='Run id to set. Need --config')

parser.add_argument('--init', action='store_true', help='Define function master/slave')
parser.add_argument('--test', action='store_true', dest='test', help='Test if spots are connected')

parser.add_argument('-c', '--color', type=str, nargs='+', dest='color', required=False, help="Color's led to command (eg. WH_4000)")
parser.add_argument('-i', '--intensity', type=int, nargs='+', dest='intensity', required=False, help="Intensity (0-100)")
parser.add_argument('-e', '--exclusive', action='store_true', help='Shutdown unmentioned colors')
parser.add_argument('--play', type=int, nargs=1, dest='play', help='Play the recipe form the data base (--config needed and erases --color and --intensity)')

parser.add_argument('--off', action='store_true', help='Turn off all spots')
parser.add_argument('--on', action='store_true', help='Turn on all spots')
parser.add_argument('--shutdown', action='store_true', help='Shutdown all channels')

parser.add_argument('--snapshot', type=str, nargs='+', dest='snapshot', required=False, help='Save a snapshot in file given as first argument. The second argument specify the resolution (default 640x480)')
parser.add_argument('--info', type=str, nargs='+', dest='info', required=False, help='Return desired spots info')
parser.add_argument('--output', type=str, nargs=1, required=False, dest="out_file", help='Destination file for info data (ignored if --info is not set to all)')
parser.add_argument('--input', type=str, nargs=1, dest='in_file', required=False, help="File containing grid settings (as returning by '... --info all --output-file *.json' command) used for faster controls")
parser.add_argument('--config', type=str, nargs=1, dest='conf_file', required=False, help='Configuration file (YAML)')
parser.add_argument('--logdb', action='store_true', required=False, help='Log in database (need --config)')

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
# Snapshot
if args['snapshot'] != None:
	verbose("Capturing image from camera")
	import subprocess
	dest_file = args['snapshot'][0]
	res = "640x480"
	if len(args['snapshot'])>1 != None: # donne une résolution en argument
		res = args['snapshot'][1]
	cmd = "fswebcam -r "+res+" --jpeg 85 -D 1 --quiet "+dest_file
	exit_code = subprocess.call(cmd, shell=True)
	if exit_code == 0:
		verbose("... done")
	else:
		verbose("... an error occured")
	sys.exit()
# -----------------------------------------------------------------------------
# Chargement depuis un fichier de configuration (compatibilité avec interface web)
if args["conf_file"] != None:
	if os.path.isfile(args["conf_file"][0]):
		with open(args["conf_file"][0], 'r') as stream:
			try:
				config_yaml = yaml.load(stream)
				verbose("Loading configuration...")
				# Port
				args["port"] = config_yaml["PORT"]
				# Base de données
				conn = sqlite3.connect(config_yaml["DB"])
				conn.row_factory = sqlite3.Row # sortie des 'fetch' en dictionnaire
				cursor = conn.cursor()
				# Liste des spots
				if args["spots"] == None:
					spots_add_list = []
					if args['cluster'] != None:
						for c in args['cluster']:
							for r in cursor.execute('SELECT address FROM luminaire WHERE cluster_id=?', (c,)):
								spots_add_list.append(r)
					else:
						cursor.execute("SELECT address FROM luminaire")
						spots_add_list = cursor.fetchall()
					# Pour l'option logdb (!!!! suite après activation des spots !)
					if args["logdb"] == True:
						cursor.execute("SELECT l0_.address AS address FROM luminaire l0_ \
							LEFT JOIN luminaire_luminaire_status l2_ ON l0_.id = l2_.luminaire_id \
							LEFT JOIN luminaire_status l1_ ON l1_.id = l2_.luminaire_status_id \
							WHERE l1_.code < 99;")
						spots_add_list = cursor.fetchall()
					# Formatage de la liste
					if len(spots_add_list) > 0:
						args["spots"] = list(map(lambda x: int(x[0]),spots_add_list)) # fetchall renvoit une liste de tuple
					else:
						args["spots"] = None
				
			except yaml.YAMLError as exc:
				config_yaml = None
				verbose("YAML exeption: "+exc)
	else:
		verbose("No file "+str(args["conf_file"][0])+" found")
# -----------------------------------------------------------------------------
# Run
if args['set_run']	!= None:
	if args["conf_file"] is None:
		sys.exit("ERROR: --set-run need --config")
	verbose("Setting new run")
	# Structure de la table 'run' :
	# id (int, autoincrement), cluster_id (int), program_id (int), start (datetime), label (varchar), description (clob), date_end (datetime), status (varchar)
	run_column_names = ['id', 'cluster_id', 'program_id', 'label', 'description', 'date_end', 'status', 'start']
	cursor.execute('SELECT * FROM run WHERE id=?', (str(args['set_run'][0]),))
	run_row = cursor.fetchone()
	run_dict = {}
	for i in range(0, len(run_row)):
		run_dict[run_column_names[i]] = run_row[i]

	# Structure de la table 'program'
	# id (int, autoincrement), label (varchar), description (clob)
	prog_column_names = ['id', 'label', 'description']
	cursor.execute('SELECT * FROM program WHERE id=?', (run_dict['program_id'],))
	prog_row = cursor.fetchone()
	prog_dict = {}
	for i in range(0, len(prog_row)):
		prog_dict[prog_column_names[i]] = prog_row[i]

	# Structure de la table 'step'
	# id (int, autoincrement), program_id (int), recipe_id (int), type (varchar), rank (int), value (varchar)
	step_column_names = ['id', 'program_id', 'recipe_id', 'type', 'rank', 'value']
	cursor.execute('SELECT * FROM step WHERE program_id=?', (prog_dict['id'],))
	step_listdict = []
	for r in cursor.fetchall():
		tmp_dict = {}
		for i in range(0, len(r)):
			tmp_dict[step_column_names[i]] = r[i]
		step_listdict.append(tmp_dict)

	# Remplissage de la table run_step
	time = datetime.strptime(run_dict['start'], "%Y-%m-%d %H:%M:%S")
	goto = -1
	i = 0
	while i <= (len(step_listdict)-1):
		if step_listdict[i]['type'] != "goto":
			cmd = "--cluster "+str(run_dict['cluster_id'])
			if step_listdict[i]['type'] == "off":
				cmd = cmd+" --off"
			if step_listdict[i]['type'] == "time":
				cmd = cmd+" -e --play "+str(step_listdict[i]['recipe_id'])
			cursor.execute('INSERT INTO run_step(run_id, start, command, status) VALUES (?,?,?,?)', (str(args['set_run'][0]), time, str(cmd), 0,))
			time = time + timedelta(hours = int(step_listdict[i]['value'].split(":")[0]), minutes = int(step_listdict[i]['value'].split(":")[1]))
		else:
			if goto < 0:
				goto = int(step_listdict[i]['value'].split(":")[1])
			if goto == 0:
				goto = -1
			if goto > 0:
				i = int(step_listdict[i]['value'].split(":")[0]) -1 # car +1 juste après (boucle)
				goto = goto - 1			
		i = i+1

	# Mise à jour de la table run par le champ date_end
	cursor.execute('UPDATE run SET date_end = ? WHERE id = ?', (time, str(args['set_run'][0]),))
	# Sauvegarde
	conn.commit()
	verbose("... done")
	sys.exit()
# -----------------------------------------------------------------------------
# Vérification du port et de la liste des spots (indispensable)
if args["port"] == None:
	verbose("No port given\nEXIT")
	sys.exit()
if args["spots"] == None:
	verbose("No spot's adress given\nEXIT")
	sys.exit()
# -----------------------------------------------------------------------------
# Création du réseau de spots (non chargés)
verbose("Initialization")
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
# Nouveau réseau
g = velire.Grid()
g.new(spots_add=spots_ad_list, port=args["port"])
g.open()
# Vérifie les spots connectés et élimine les autres
spot_found = g.find_spot()
verbose("... searching spot on grid")
spot_found = g.find_spot()
verbose("... "+str(len(spot_found["found"]))+" spot(s) found")
verbose("... "+str(len(spot_found["not_found"]))+" spot(s) NOT found")
if len(spot_found["not_found"]) > 0: # nouvel grille avec seulement les spots détecté
	g.close()
	g = velire.Grid() # écrase
	g.new(spots_add=spot_found["found"], port=args["port"])
	g.open()
verbose("... done")
# -----------------------------------------------------------------------------
# Recherche des spots sur le réseau
if args["test"] == True:
	if args["json"] == True:
		print(json.dumps(spot_found))
	else:
		pprint.pprint(spot_found)
	verbose("... done")
	sys.exit()
# -----------------------------------------------------------------------------
# Défini le rôle de master/slave
if args["init"] == True:
	verbose("Setting master/slave")
	verbose("... setting function")
	for s in g.spots_list:
		if int(s.address) == int(min(spot_found['found'])):
			r = s.set_ms(master=True)
			verbose("... ... spot at "+str(s.address)+" defined as MASTER")
		else:
			r = s.set_ms(master=False) # tous esclaves
			verbose("... ... spot at "+str(s.address)+" defined as slave")
	if len(spot_found['not_found']) > 0:
		verbose("... spot not found: "+str(spot_found['not_found']))
	verbose("... done")
# -----------------------------------------------------------------------------
# Chargement de la configuration des spots: via un fichier ou depuis la mémoire des luminaires
if args["in_file"] != None:
	loading_from_hardware = 0
	verbose("Loading configuration from file "+args["in_file"][0])
	with open(args["in_file"][0]) as f:
		data = json.load(f)
	g.activate2(data)
	verbose("... done")
else:
	loading_from_hardware = 1
	verbose("Loading configuration from hardware")
	g.activate() # depuis les infos des luminaires
	verbose("... done")
# -----------------------------------------------------------------------------
# Informations
if args["info"] != None:
	if "all" in args["info"]:
		if loading_from_hardware != 1:
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
# Log in data-base			
if args["logdb"] == True:
	verbose("Log informations into data base")
	if args["conf_file"] is None:
		sys.exit("ERROR: --set-run need --config")
	# Timestamp
	timenow = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
	# Lecture des infos des spots
	if loading_from_hardware != 1:
		g.activate()
	infos = g.get_info()
	# Formatage des données
	for k,v in infos['spots'].items():
		data = {}
		# Lecture de la BD des spots
		cursor.execute('SELECT * FROM luminaire WHERE address = ?', (v['address'],))
		spotsdb = cursor.fetchone()
		# Remplissage du dictionnaire
		data['address'] = v['address']
		data['serial'] = v['serial']
		data['led_pcb_0'] = v['temperature']['led_pcb_0']
		data['led_pcb_1'] = v['temperature']['led_pcb_1']
		data['channels_on'] = {}
		for kk,vv in v['channels'].items():
			if vv['intensity'] > 0:
				data['channels_on'][str(kk)] = {'color' : vv['color'], 'intensity': vv['intensity']}
		#pprint.pprint(data)
		data = json.dumps(data)
		cursor.execute('INSERT INTO Log(time, type, luminaire_id, cluster_id, value, comment) \
			VALUES  (?,?,?,?,?,?)', (timenow, "luminaire_info", spotsdb['id'], spotsdb['cluster_id'], data, "",))
	# Sauvegarde
	conn.commit()
	verbose("Done !")
	sys.exit()
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
# Chargement du controle de l'intensité depuis la base de données 
if args['play'] != None:
	if 'cursor' in locals() or 'cursor' in globals():
		verbose("Recipe loading from data base")
		# Récupère les info sur les LED
		cursor.execute("SELECT l.type, l.wavelength FROM ingredient i LEFT JOIN led l ON l.id=i.led_id WHERE recipe_id=? ORDER BY i.id", (str(args['play'][0]),))
		args["color"] = list(map(lambda x: str(x[0])+"_"+str(x[1]),  cursor.fetchall())) # tuple -> list + concaténation pour code couleur
		# Récupère les niveaux d'intensités
		cursor.execute("SELECT i.level FROM ingredient i LEFT JOIN led l ON l.id=i.led_id WHERE recipe_id=? ORDER BY i.id", (str(args['play'][0]),))
		args["intensity"] = list(map(lambda x: int(x[0]),  cursor.fetchall()))
		if len(args["intensity"]) == 0 or len(args["color"]) == 0:
			verbose("No recipe found")
	else:
		verbose("Recipe loading from data base failed")
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
	values = [1,7,15,20,30,42,30,20,15,7]
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
				reply = grid.set_bycolor({"colortype": c, "intensity" : 1, "unit": "%", "start": 0, "stop": 1})
				sleep(time_sleep)
				reply = grid.set_bycolor({"colortype": c, "intensity" : 0, "unit": "%", "start": 0, "stop": 1})

if args["demo"] != None:
	g.set_state(1)
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