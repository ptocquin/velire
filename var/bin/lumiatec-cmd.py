#!/usr/bin/env python3
# -*- coding: utf-8 -*-

# ----------------------------------------------------------------------------
# LUMIATEC COMMANDER
# ----------------------------------------------------------------------------
# Anthony Fratamico
# Laboratoire de Physiologie Végétale
# Institut de Botanique, Université de Liège
# 11 juin 2020
# ----------------------------------------------------------------------------
# Programme de contrôle des luminaires Lumiatec
# ----------------------------------------------------------------------------
__author__ = "Anthony Fratamico"
__version__ = "0.0 (dev)"
__license__ = ""
# ============================================================================
# BIBLIOTHÈQUES
# ----------------------------------------------------------------------------
import os
import sys
import platform
import argparse
import logging
import json
from pprint import pprint
# ============================================================================
# VARIABLES GÉNÉRALES
# ----------------------------------------------------------------------------
os_info = {
'name': os.name,
'sys': platform.system(),
'release': platform.release()
}
error_codes = {
	0: 'No error',
	1: 'Not referenced errors',
	2: 'Args error',
	10: 'General Errors',
	20: 'Missing args',
	30: 'Communication error',
	40: 'Error in read/write file',
	50: 'Action failed'
}
# ============================================================================
# ARGUMENTS
# ----------------------------------------------------------------------------
parser = argparse.ArgumentParser(description='Control LUMIATEC Lightings.')

# Général
parser.add_argument('--version', action='version', version='%(prog)s {version}'.format(version=__version__),\
	help='Print version')

# Log
parser.add_argument('--verbose', '-v', action='count', default=0,
	help='Increase verbosity: "\
	"-v) Critical -vv) Error -vvv) Warning (default)"\
	"-vvvv) Info -vvvvv) Debug')
parser.add_argument('--log', '-l', type=str, dest='log', 
	help='File for logging')
parser.add_argument('--quiet', '-q', action='store_true', dest='quiet',
	help='No debug message is printed in standard output. "\
	"Keep writing logging if --log is specified')

# Utilitaires
parser.add_argument('--get-error-dict', action='store_true', dest='error_dict',
	help='Return dictionnary of error signals and exit')
parser.add_argument('--no-update', action='store_true', dest='no_update',
	default=False, help='Get the lats infos without update of parameters')

# Infos élémentaires
parser.add_argument('--model', '-m',
	type=str, dest='model', required=True,
	help="Model of lighting")
parser.add_argument('-p', '--port', type=str, dest='port', required=False,
	help="Port for communication (eg. /dev/ttyUSB0)")
parser.add_argument('-a', '--address', type=str, nargs='+', dest='add', required=False,
	help="Adresses")

# Tests
parser.add_argument('--search', action='store_true', dest='search',
	help='Search for spots given in --add')
parser.add_argument('--check', action='store_true', dest='check',
	help='Check for config error on network and return 0 (no error) or 1 (error)')
parser.add_argument('--debug',
	action="store_true", dest='debug', required=False,
	help="Enter in debug mode, low level communication, for developpers")

# Actions
parser.add_argument('-I', '--get-info', type=str, nargs='*', dest='infos', required=False,
	help="Gives some informations given in arguments. Use 'all' for a complete list")
parser.add_argument('-M', '--set-master', type=int, nargs='?', dest='master',\
	default=None, const=-1, required=False,\
	help="Define the address given as argument as master. A negative value (default) produce automatic choise")
parser.add_argument('-P', '--set-power', type=int, dest='power', required=False,
	help="Turn driver ON/OFF: Off: 0; On: any other value")
parser.add_argument('-F', '--set-freq', type=float, dest='freq', required=False,
	help="Define the light'frequency")
parser.add_argument('-O', '--set-overdrive', type=int, dest='overdrive', required=False,
	help="Define the overdrive: 0) 50 1) 75 2) 100")
parser.add_argument('-S', '--set-colors', type=str, nargs='+', dest='set_colors', required=False,
	help="Setting as [colors] [intensity] [PWM start point] [PWM stop point].")
parser.add_argument('-D', '--shutdown', action='store_true', dest='shutdown', required=False,
	help="Shutdown all channels")
parser.add_argument('-W', '--write', action='store_true', dest='write', required=False,
	help="Write the current configuration as default in spot EEPROM")
parser.add_argument('-R', '--restart', action='store_true', dest='restart', required=False,
	help="Restart spots like truning ON")
parser.add_argument('--upload-firmware', type=str, nargs=1, dest='firmware_file', required=False,
	help="Flash firmware")

# Option d'actions
parser.add_argument('-e', '--exclusive', action='store_true', dest='exclusive', required=False,
	help="Turn off the colors no given in --set-colors")
parser.add_argument('-j', '--json', action='store_true', dest='json', required=False,
	help="Output is JSON formatted")
parser.add_argument('-o', '--output-file', type=str, nargs=1, dest='output_file', required=False,
	help="Redirect output to file")

args = vars(parser.parse_args())
# ============================================================================
# FONCTIONS
# ----------------------------------------------------------------------------
def write_output(data, formating='print', file=None):
	if file is None:
		if formating == 'json':
			print(json.dumps(data))
		elif formating == "pprint":
			pprint(data)
		else:
			print(data)
	else:
		file = file[0]
		if formating == 'json':
			try:
				with open(file, 'w') as f:
					json.dump(data, f)
			except:
				logger.error("Unable to write file")
				sys.exit(40)
		else:
			try:
				with open(file, 'w') as f:
					f.write(str(data))
			except:
				logger.error("Unable to write file")
				sys.exit(40)
# ============================================================================
# LOGGING [Adapté de https://docs.python.org/2/howto/logger-cookbook.html]
# ----------------------------------------------------------------------------
# Logger
logger = logging.getLogger(__name__)
logger.setLevel(logging.DEBUG) # par défaut
# Niveau de verbosité
verboseLevels = [
logging.WARNING,
logging.CRITICAL,
logging.ERROR,
logging.WARNING,
logging.INFO,
logging.DEBUG
]
if args['verbose'] >= len(verboseLevels):
	args['verbose'] = len(verboseLevels)-1 
verbosity = verboseLevels[args['verbose']]
# Fichier d'historique
if args['log'] is not None:
	# Crèe le dossier si n'existe pas
	if (os.path.dirname(args['log']) != "") and\
	(not os.path.isdir(os.path.dirname(args['log']))):
		try:
			os.mkdir(os.path.dirname(args['log']))
		except:
			print("Unable to create directory for log file")
	fileLog = logging.FileHandler(args['log'])
	fileLog.setLevel(verbosity)
	fileLog.setFormatter(logging.Formatter(\
		'%(asctime)s :: %(name)s :: %(levelname)s :: %(message)s'))
	logger.addHandler(fileLog)
# Sortie standard
stdLog = logging.StreamHandler()
if args['quiet']:
	stdLog.setLevel(sys.maxsize)
else:
	stdLog.setLevel(verbosity)
stdLog.setFormatter(logging.Formatter('[%(levelname)s] %(message)s'))
logger.addHandler(stdLog)
# Démarrage
logger.info("Opening. Release "+__version__)
# ============================================================================
# UTILITAIRES
# ----------------------------------------------------------------------------
print_format = 'pprint'
if args['json']:
	print_format = 'json'
output_file = args['output_file']
# ----------------------------------------------------------------------------
if args['error_dict']:
	write_output(data=error_codes, formating=print_format, file=output_file)
	sys.exit(0)
# ============================================================================
# CHARGEMENT DE LA BIBLIOTHÈQUE DU MODÈLE DE LUMINAIRES
# ----------------------------------------------------------------------------
logger.info("Loading library")
from lumiatec import lumiatec_phs16 as mod
if args['model'] == "lumiatec-phs16":
	# Chargement de la bibliothèque
	try:
		from lumiatec import lumiatec_phs16 as mod
		logger.debug("Library lumiatec_phs16 loaded")
	except:
		logger.critical("Unable to load library lumiatec_phs16")
		sys.exit(10)
	# Vérification des arguments requis
	if not args['debug']:
		if args['port'] is None:
			logger.critical("Model 'lumiatec-phs16' need --port")
			sys.exit(20)
		if args['add'] is None:
			logger.critical("No adresses given")
			sys.exit(20)
else:
	logger.critical("Library for model '"+args['model']+"'' not found")
	sys.exit(10)
# Niveau de verbosité de la bibliothèque
mod.setLog(verbosity=verbosity, file=args['log'], quiet=args['quiet'])
# ============================================================================
# DEBUGGAGE EN LIGNE DE COMMANDE
# ----------------------------------------------------------------------------
if args['debug']:
	logger.info("Debug mode")
	mod.debugcom()
	sys.exit(0)
# ============================================================================
# CHARGEMENT D'UN NOUVEAU FIRMWARE
# ----------------------------------------------------------------------------
if args['firmware_file'] is not None:
	print(args['firmware_file'])
	logger.info("Flashing new firmware")
	if len(args['add']) != 1 :
		logger.error("Flashing firmware only supported for one spot")
		sys.exit(10)
	err = mod.upload_firmware(spot=args['add'][0], port=args['port'], file=args['firmware_file'][0])
	sys.exit(err)
# ============================================================================
# OUVERTURE DU RESEAU
# ----------------------------------------------------------------------------
com = mod.Com(port=args['port'])
err = com.connexion()
if err != 0:
	logger.critical("Unable to open port")
	sys.exit(30)
netw = mod.Network(com=com, spots=args['add'])
err = netw.activate()
if err != 0:
	logger.critical("Unable to activate network")
	sys.exit(1)
# Mise à jour à la commande
update = not args['no_update']
# ============================================================================
# TESTS
# ----------------------------------------------------------------------------
if args['check']:
	out = netw.check(update=True)
	write_output(data=out, formating=print_format, file=output_file)
	sys.exit(0)
if args['search']:
	out = netw.find_spots(spots=args['add'])
	write_output(data=out, formating=print_format, file=output_file)
	sys.exit(0)
# ============================================================================
# CONFIGURATION
# ----------------------------------------------------------------------------
config_list = [args['master'], args['power'], args['freq'], args['overdrive']]
if config_list.count(None) != len(config_list):
	logger.info("Configuration")
	err = netw.set_config(master=args['master'], power=args['power'],\
		freq=args['freq'], overdrive=args['overdrive'])
	if err != 0:
		logger.error("Setting configuration failed !")
		sys.exit(50)
# ============================================================================
# REGLAGE PAR COULEURS
# ----------------------------------------------------------------------------
if args['set_colors'] is not None:
	logger.info("Setting colors")
	settings = {0: {'color':[], 'intensity': None, 'start': None, 'stop': None}}
	i = 0
	val = None
	color = None
	for s in args['set_colors']:
		try:
			val = float(s)
		except:
			color = str(s)
		if color is not None and val is not None:
			i += 1
			settings[i] = {'color':[color], 'intensity': None, 'start': None, 'stop': None}
			val = None
		if color is not None and val is None:
			settings[i]['color'].append(color)
			color = None
		if val is not None:
			if settings[i]['intensity'] is None:
				settings[i]['intensity'] = val
			elif settings[i]['start'] is None:
				settings[i]['start'] = val
			elif settings[i]['stop'] is None:
				settings[i]['stop'] = val
			else:
				logger.warning("To many settings")

	all_colors = []			
	for k,v in settings.items():
		if len(v['color']) == 0:
			continue
		else:
			v['color'] = list(set(v['color']))
			all_colors.extend(v['color'])
		if v['intensity'] is None:
			v['intensity'] = 0
		if v['start'] is None:
			v['start'] = 0
		if v['stop'] is None:
			v['stop'] = 1

		err = netw.set_colors(\
			colors=v['color'],\
			intensity=v['intensity'],\
			pwm_start=v['start'],\
			pwm_stop=v['stop'])
		
		if err != 0:
			logger.error("Setting colors failed !")
			sys.exit(50)

	available_color = netw.get_infos(what='available_colors')['available_colors']
	unavailable_color = list(set(all_colors) - set(available_color))
	notused_color = list(set(available_color) - set(all_colors))
	if len(unavailable_color) > 0:
		logger.warning("Following colors was not available: "+str(unavailable_color))
	if args['exclusive'] and len(notused_color) > 0:
		logger.info("Exclusivity: followings colors will be shutdown: "+str(notused_color))
		netw.shutdown(colors=notused_color)
# ============================================================================
# EXTINCTION
# ----------------------------------------------------------------------------
if args['shutdown']:
	logger.info("Shutdown")
	err = netw.shutdown()
	if err != 0:
		logger.error("Shutdown failed !")
		sys.exit(50)
# ============================================================================
# ENREGRISTREMENT DANS LA MÉMOIRE
# ----------------------------------------------------------------------------
if args['write']:
	logger.info("Write config in EEPROM")
	err = netw.write()
	if err != 0:
		logger.error("Shutdown failed !")
		sys.exit(50)
# ============================================================================
# REDEMARRAGE
# ----------------------------------------------------------------------------
if args['restart']:
	logger.info("Restart")
	err = netw.restart()
	if err != 0:
		logger.error("Restart failed !")
		sys.exit(50)
# ============================================================================
# INFOS
# ----------------------------------------------------------------------------
if args['infos'] is not None:
	logger.info("Get informations")
	infos = netw.get_infos(what=args['infos'], update=update)
	write_output(data=infos, formating=print_format, file=output_file)
# ============================================================================
# SORTIE
# ----------------------------------------------------------------------------
netw.close()
logger.info("End of script")
sys.exit(0)