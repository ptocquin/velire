#!/usr/bin/env python3
# -*- coding: utf-8 -*-

# ----------------------------------------------------------------------------
# MODULE LUMIATEC PHS16
# ----------------------------------------------------------------------------
# Anthony Fratamico
# Laboratoire de Physiologie Végétale
# Institut de Botanique, Université de Liège
# 11 juin 2020
# ----------------------------------------------------------------------------
# Bibliothèque de contrôle des luminaire Lumiatec PHS-16
# ----------------------------------------------------------------------------
__author__ = "Anthony Fratamico"
__version__ = "0.0 (dev)"
__license__ = ""
# ============================================================================
# BIBLIOTHÈQUES
# ----------------------------------------------------------------------------
import os
import serial
import logging
from datetime import datetime
import copy
from itertools import repeat
# ============================================================================
# LOGGING # Adapté de https://docs.python.org/2/howto/logger-cookbook.html
# ----------------------------------------------------------------------------
# Logger
logger = logging.getLogger(__name__)
logger.setLevel(logging.DEBUG) # par défaut
# ----------------------------------------------------------------------------
def setLog(verbosity, file=None, quiet=False):
	if quiet == False:
		stdLog = logging.StreamHandler()
		stdLog.setLevel(verbosity)
		stdLog.setFormatter(logging.Formatter(\
			'[%(levelname)s] (%(name)s:%(funcName)s) %(message)s'))
		logger.addHandler(stdLog)
	if file is not None:
		# Crèe le dossier si n'existe pas
		if (os.path.dirname(file) != "") and\
		(not os.path.isdir(os.path.dirname(file))):
			try:
				os.mkdir(os.path.dirname(file))
			except:
				print("Unable to create directory for log file")
				return 1
		# Logger
		fileLog = logging.FileHandler(file)
		fileLog.setLevel(verbosity)
		fileLog.setFormatter(logging.Formatter(\
			'%(asctime)s :: %(name)s:%(funcName)s :: %(levelname)s :: %(message)s'))
		logger.addHandler(fileLog)
	return 0
# valeur par défaut
setLog(logging.WARNING)
# ============================================================================
# VARIABLES GÉNÉRALES
# ----------------------------------------------------------------------------
date_format = "%Y-%m-%d %H:%M:%S.%f"
# ============================================================================
# FONCTIONS
# ----------------------------------------------------------------------------
def debugcom():
	""" Communication interactive avec le port série

	return: None
	"""
	logger.debug("CALL")
	# Configuration du port
	serinfos = {
	'port': "/dev/ttyUSB0",
	'baudrate': "115200",
	'timeout': "1"
	}
	print("Port configuration (press ENTER to accept default values)")
	for k in ['port', 'baudrate', 'timeout']:
		try:
			ask = input("   "+k+" [default: "+str(serinfos[k])+"]: ")
			if ask != "":
				serinfos[k] = str(ask)
		except KeyboardInterrupt:
			print("\nEXIT")
			return
	serinfos['baudrate'] = int(serinfos['baudrate'])
	serinfos['timeout'] = float(serinfos['timeout'])
	logger.debug("Serial port configuration: "+str(serinfos))
	# Ouverture du port
	com = Com(port=serinfos['port'],
			baudrate=serinfos['baudrate'],
			timeout=serinfos['timeout'])
	error = com.connexion()
	if error != 0:
		logger.critical("Unable to open serial port")
		return
	# Communication
	print("Communication in autocomplete mode")
	while True:
		send = ""
		reply = ""
		try:
			command = input("COMMAND : ")
			cmd = command.split(" ")[0]
			add = command.split(" ")[1]
			arg = " ".join(command.split(" ")[2:])
			out = com.send(cmd=cmd, prefix=add, arg=arg)
			logger.debug("Reply: "+str(out))
			print("   SENDED: " + out['cmd'])
			print("   REPLY: " + out['raw'])
		except KeyboardInterrupt:
			logger.debug("Keyboard interrupt received")
			print("\nEXIT")
			break
	return
# ----------------------------------------------------------------------------
def decrypt_led(string, length, coding):
	""" Décrypte le descriptif détaillé des LED
	
	string: chaîne complète
	length: longueur (en bit)
	type: stype de sorte: char, hex ou int

	return: liste. 1er élément, le décrypté. 2e le reste de la chaîne
	"""
	# Subset
	data = string[0:int(2*length/8)]
	remainder = string[int(2*length/8):]
	# Division par 2 charactères
	data = [data[i:i+2] for i in range(0, len(data), 2)]
	# LSB first ?
	if length > 8:
		data.reverse()
	# Type
	data = "".join(data)
	if coding == "text":
		try:
			data = bytearray.fromhex(data).decode()
		except:
			data = None
	elif coding == "int":
		try:
			data = int(data, 16)
		except:
			data = None
	elif coding == "hex":
		try:
			data = "0x"+data
		except:
			data = None
	return data, remainder
# ----------------------------------------------------------------------------
def convert(x, type_out):
	try:
		y = type_out(x)
	except:
		y = None
	return y

def convert_tolistof(data, type_out, none_omit=False):
	if type(data) is not list:
		data = [data]
	out = list(map(convert, data, repeat(type_out)))
	if none_omit:
		out = list(filter(None.__ne__, out))
	return out
# ----------------------------------------------------------------------------
def upload_firmware(spot, port, file):
	logger.debug("CALL")
	# Vérification du spot
	try:
		spot = int(spot)
	except:
		logger.error("Spot address must be an integer")
		return 1
	# Port série
	logger.debug("Initialize serial port")
	com = Com(port=port)
	error = com.connexion()
	if error != 0:
		return 1
	# Recherche du spot
	logger.debug("Test addresse")
	reply = com.send("GI", prefix=spot)
	if reply['reply'] == "":
		logger.debug("   ... address not found")
		logger.error("Address not found")
		return 1
	else:
		old_version = reply['reply']
		logger.debug("   ... found. Current version: "+str(old_version))
	# Chargement du module XMODEM
	logger.debug("Import module XMODEM")
	try:
		from xmodem import XMODEM
	except:
		logger.critical("Unable to load module XMODEM")
		return 1
	def getc(size, timeout=1):
		return com.ser.read(size) or None
	def putc(data, timeout=1):
		return com.ser.write(data)
	modem = XMODEM(getc, putc)
	try:
		stream = open(file, 'rb')
	except:
		logger.critical("Unable to open file")
		return 1
	# Préparation du Spot
	logger.debug("Initialization of reception")
	reply = com.send(cmd="UP", prefix=spot, arg=1)
	if reply['reply'] == "ACK":
		logger.debug("   ... ready")
	else:
		logger.debug("   ... failed !")
		logger.error("unable to initialize reception")
		return 1
	# Envoi
	logger.debug("Sending")
	reply = modem.send(stream)
	if reply == True:
		logger.debug("   ...done")
	else:
		logger.debug("   ... failed !")
		logger.debug("   ... restart CPU")
		reply = com.send(cmd="RST", prefix=spot)
		return 1
	# Flash
	logger.debug("Flashing")
	reply = com.send(cmd="FF", prefix=spot)
	print(reply)
	# Restart
	logger.debug("Restart CPU")
	reply = com.send(cmd="RST", prefix=spot)
	print(reply)
	# Check nouvelle version
	logger.debug("Checking version")
	reply = com.send("GI", prefix=spot)
	logger.debug("  ... new version: "+str(reply['reply']))
	return 0
# ============================================================================
# CLASSES
# ----------------------------------------------------------------------------
class Com():
	def __init__(self, port, baudrate=115200, timeout=1):
		self.port = port
		self.baudrate = baudrate
		self.timeout = timeout

	def get_ports(self):
		logger.debug("CALL")
		import serial.tools.list_ports
		available_ports = []
		for p in  list(serial.tools.list_ports.comports()):
			p = tuple(p)
			available_ports.append(p[0])
		return available_ports

	def connexion(self):
		logger.debug("CALL")
		# Vérification de la présence du port
		if self.port not in self.get_ports():
			logger.critical("Port "+str(self.port)+" not found")
			return 1
		# Connexion
		try:
			self.ser = serial.Serial(port=self.port, baudrate=self.baudrate, timeout=self.timeout)
			self.ser.isOpen()
			logger.debug("Port is opened")
			return 0
		except IOError:
			logger.critical("Unable to open port")
			return 1

	def open(self):
		if not self.ser.isOpen():
			try:
				self.ser.open()
			except:
				logger.error("Unable to open port")
				return 1
		return 0

	def close(self):
		if self.ser.isOpen():
			try:
				self.ser.close()
			except:
				logger.error("Unable to close port")
				return 1
		return 0

	def compute_checksum(self, string):
		""" Calcul la somme de contôle

		string: chaine de caractères dont la somme doit être calculée

		Retrun: integer
		"""
		checksum = 0
		for x in list(string):
			checksum = checksum + ord(x)
		return checksum % 256

	def send(self, cmd, prefix="", arg="", checksum=True):

		""" Envoie une commande sur ser et renvoi la réponse

		ser: Objet serial
		cmd: commande à excécuter
		add: addresse du spot
		arg: les arguments de la commande (cf. dictionnaire des commandes)
		checksum: vérifie la communication (True/False)

		return: dict
		"""
		# ------------------------------------------------------------------------
		
		# ------------------------------------------------------------------------
		#logger.debug("CALL")
		# Dictionnaire pour la sortie
		reply_dict = {
		'raw': None,
		'cmd': None,
		'checksum': None,
		'reply': None,
		'log': None,
		'error': True}
		# Vérifie que le port est ouvert
		error = self.open()
		if error != 0:
			reply_dict['log'] = "Unable to open serial port"
			logger.error(reply_dict['log'])
			return reply_dict
		# Préparation de la commande balisée
		prefix = convert_tolistof(data=prefix, type_out=str)
		prefix = " ".join(prefix)
		arg = convert_tolistof(arg, type_out=str)
		arg = " ".join(arg)
		cmdbal = str("@"+str(cmd)+" "+prefix+" "+arg)
		if checksum:
			cmdbal = cmdbal+"*"+str(self.compute_checksum(cmdbal))
		cmdbal += "\r"
		reply_dict['cmd'] = cmdbal
		# Envoie de la commande
		self.ser.write(cmdbal.encode()) # encodage ascii nécessaire
		# Écoute de la réponse
		reply = self.ser.read_until(str("\r").encode())
		self.ser.flushInput() # flush, utile si timeout dépassé
		self.ser.flushOutput()
		# Sauvegarde de la réponse brute
		reply_dict['raw'] = str(reply)
		# Decodage simple
		head = "#"+cmd+" "
		queue = "*"
		cleaning = False
		try:
			reply = reply.decode()
			reply = reply.lstrip("\x00") # bytes nulle reçu avant. Signe de ligne poluée?
		except:
			cleaning = True
		# Analyse de la réponse
		if cleaning == False:
			reply = reply.split("\r", 1)[0]
			if (reply.startswith(head)) and (queue in reply): # réponse structurée
				reply_dict['reply'] = reply.split(head, 1)[1] # supprime l'entête
				reply_dict['reply'] = reply_dict['reply'].split(queue, 1)[0]
				reply_dict['checksum'] = self.compute_checksum(reply.split(queue, 1)[0])
				if reply_dict['checksum'] == int(reply.split(queue, 1)[1], 16):
					reply_dict['error'] = False
				else:
					reply_dict['log'] = "Wrong checksum"
			elif reply == "ACK" or reply.startswith("NAK"):
				reply_dict['reply'] = reply
				reply_dict['error'] = False
			else:
				reply_dict['reply'] = ""
				reply_dict['log'] = "Wrong reply structure"
		else:
			logger.warning("Unexpected bytes received. Trying to clean.")
			# décodage en cas de ligne poluée et réception de bytes en dehors de la réponse
			reply = reply.split("\r".encode(), 1)[0]
			if (head.encode() in reply) and (queue.encode() in reply): # réponse structurée

				reply = reply.split(head.encode(), 1)[1] # supprime l'entête et avant
				checksum = reply.split(queue.encode(), 1)[1]
				reply = reply.split(queue.encode(), 1)[0]

				try:
					reply_dict['reply'] = reply.decode()
					checksum = int(checksum.decode(), 16)
				except:
					reply_dict['reply'] = ""
					reply_dict['log'] = "Unable to decode reply string even after cleanning"
					return reply_dict

				reply_dict['checksum'] = self.compute_checksum(head+reply_dict['reply'])
				if reply_dict['checksum'] == checksum:
					reply_dict['error'] = False
				else:
					reply_dict['log'] = "Wrong checksum"

			elif "ACK".encode() in reply:
				reply_dict['reply'] = "ACK"
				reply_dict['error'] = False
			
			elif "NAK ".encode() in reply:
				try:
					reply_dict['reply'] = "NACK "+str(int(reply.split("NACK ".encode(), 1)[1]))
					reply_dict['error'] = False
				except:
					reply_dict['reply'] = "NACK"
					reply_dict['log'] = "Unable to decode NACK code even after cleaning"
			else:
				reply_dict['reply'] = ""
				reply_dict['log'] = "Wrong reply structure"
		
		# Sortie
		if reply_dict['error']:
			logger.error("Com. error: "+reply_dict['log'])
		return reply_dict

# ----------------------------------------------------------------------------
class Network():
	
	def __init__(self, com, spots):
		logger.debug("CALL")
		self.com = com
		# Spots
		self.spots_input = spots
		self.spots = {}
		self.available_color = []
		self.activ = None

	def find_spots(self, spots=[]):
		logger.debug("CALL")
		if len(spots) == 0:
			spots = self.spots_input
		spots = convert_tolistof(data=spots, type_out=int, none_omit=True)
		out = {'found': [], 'not-found':[]}
		for s in spots:
			reply = self.com.send(cmd="GI", prefix=s)
			if reply['error'] == False and reply['reply'] != "":
				out['found'].append(int(s))
			else:
				out['not-found'].append(int(s))
		return out

	def activate(self):
		logger.debug("CALL")
		spots_result = self.find_spots()
		if len(spots_result['found']) == 0:
			logger.error("No spot found")
			return 1
		if len(spots_result['not-found']) > 0:
			logger.warning("Following spots not found: "+str(spots_result['not-found']))
		self.spots_add = list(set(spots_result['found']))
		self.activ = datetime.now().strftime(date_format)

		error = 0
		for sa in self.spots_add:
			s = Spot(sa, self.com)
			error += s.activate()
			self.spots[sa] = s
			self.available_color.extend(s.get_available_colors())
		self.available_color = list(set(self.available_color))

		if error == 0:
			return 0
		else:
			return 1

	def close(self):
		error = self.com.close()
		return error

	def set_config(self, master=None, power=None, freq=None, overdrive=None):
		if len(self.spots.keys()) == 0:
			logger.error("No spot defined")
			return 1
		config = {}
		# Master
		if master is not None:
			try:
				master = int(master)
			except:
				logger.error("Argument 'master' must be an integer")
				return 1
			if master < 0:
				master = min(self.spots.keys())
				logger.info('Master choosen automatically at address '+str(master))
			if master not in list(self.spots.keys()):
				logger.error("Address given for master not found")
				return 1
			config['master'] = master
		# Power
		if power is not None:
			if power in [0,1]:
				config['power'] = power
			else:
				logger.error("Power value not valid")
				return 1
		# Fréquence
		if freq is not None:
			try:
				config['freq'] = float(freq)
			except:
				logger.error("Frequence must be a float")
				return 1
		# Overdrive
		if overdrive is not None:
			if overdrive in [0,1,2]:
				config['overdrive'] = overdrive
			else:
				logger.error("Overdrive value not valid")
				return 1
		# Envoi
		error = 0
		if len(config) > 0:
			for k,s in self.spots.items():
				if k == master:
					config['master'] = 1
				error += s.set_config(config=config)
		# Sortie
		if error > 0:
			return 1
		else:
			return 0

	def check(self, update=True):
		master = []
		freq = []
		config = []
		drivers = []
		for k,s in self.spots.items():
			conf = s.get_config(update=update)
			master.append(conf['master'])
			freq.append(conf['freq'])
			status = s.get_status(update=update)
			if status['config'] == "OK":
				config.append(0)
			else:
				config.append(1)
			if status['drivers']['global'] == "OK":
				drivers.append(0)
			else:
				drivers.append(1)
		error = 0
		if len(master) != 1:
			error += 1
		if len(set(freq)) != 1:
			error += 1
		if sum(config) > 0:
			error += 1
		if sum(drivers) > 0:
			error += 1
		# Sortie
		if error == 0:
			return 0
		else:
			return 1

	def get_infos(self, what=[], spots=[], update=False):
		what = convert_tolistof(data=what, type_out=str, none_omit=True)
		spots = convert_tolistof(data=spots, type_out=int, none_omit=True)
		if len(spots) == 0:
			spots = list(self.spots.keys())
		out = {}

		if ("available_colors") in what or (len(what) == 0):
			out['available_colors'] = self.available_color
		if ("temp") in what or (len(what) == 0):
			out["temp"] = {}
			for add in spots:
				out["temp"][add] = self.spots[add].get_temp(update=update)
		if ("status") in what or (len(what) == 0):
			out["status"] = {}
			for add in spots:
				out["status"][add] = self.spots[add].get_status(update=update)
		if ("config") in what or (len(what) == 0):
			out["config"] = {}
			for add in spots:
				out["config"][add] = self.spots[add].get_config(update=update)
		if ("specs") in what or (len(what) == 0):
			out["specs"] = {}
			for add in spots:
				out["specs"][add] = self.spots[add].get_specs(update=update)
		if ("channels_config") in what or (len(what) == 0):
			out["channels_config"] = {}
			for add in spots:
				out["channels_config"][add] = self.spots[add].get_channels_config(update=update)
		if ("activ_time") in what or (len(what) == 0):
			out['activ_time'] = self.activ

		return out

	def set_colors(self, colors, intensity, pwm_start=0, pwm_stop=1):
		logger.debug("CALL")
		error = 0
		for k,s in self.spots.items():
			error += s.set_colors(colors=colors, intensity=intensity,\
				pwm_start=pwm_start, pwm_stop=pwm_stop)

		if error == 0 :
			return 0
		else:
			return 1

	def shutdown(self, spots=[], colors="*"):
		spots = convert_tolistof(data=spots, type_out=int, none_omit=False)
		colors = convert_tolistof(data=colors, type_out=str, none_omit=False)
		if len(spots) == 0:
			spots = self.spots.keys()
		spots = list(set(spots) & set(list(self.spots.keys())))

		if len(spots) == 0:
			logger.warning("Spot to shutdown not found")
			return 0

		if "*" in colors:
			colors = self.available_color
		colors = list(set(colors) & set(list(self.available_color)))
		if len(colors) == 0:
			logger.warning("Color to shutdown not found")
			return 0

		error = 0
		for add in spots:
			error += self.spots[add].shutdown(colors=colors)

		if error == 0:
			return 0
		else:
			return 1

	def write(self, spots=[]):
		spots = convert_tolistof(data=spots, type_out=int, none_omit=True)
		if len(spots) == 0:
			spots = list(self.spots.keys())

		error = 0
		for add in spots:
			error += self.spots[add].write()

		if error == 0:
			return 0
		else:
			return 1

	def restart(self, spots=[]):
		spots = convert_tolistof(data=spots, type_out=int, none_omit=True)
		if len(spots) == 0:
			spots = list(self.spots.keys())

		error = 0
		for add in spots:
			error += self.spots[add].restart()

		if error == 0:
			return 0
		else:
			return 1

# ----------------------------------------------------------------------------
class Spot():
	""" Unité de base

	"""
	def __init__(self, address, com):
		logger.debug("CALL")
		self.add = address
		self.com = com
		self.firmvers = (0,0)
		self.specs = {
		'address': None,
		'firmware-version': None,
		'SN': None,
		'pcb-led': None,
		'timestamp': None
		}
		self.status = {
		'config': None,
		'drivers': {'global': None, 'descr' : None},
		'timestamp': None
		}
		self.temp = {
		'cpu': None,
		'pcb-led': None,
		'timestamp': None
		}
		self.config = {
		'master': None,
		'power': None,
		'freq': None,
		'overdrive': None
		}
		self.ch_config = {}
		self.channels = {}
		self.channels_bycolor = {}
		self.available_color = []

	def coms(self, cmd, arg=""):
		logger.debug("CALL")
		reply = self.com.send(cmd=cmd, prefix=self.add,
			arg=arg, checksum=True)
		#print(reply)
		if str(reply['reply']).startswith("NAK"):
			logger.warning("Com. received code "+str(reply['reply']))
		if reply['error'] == True:
			return None
		else:
			return reply['reply']

	def activate(self):
		logger.debug("CALL")
		error = 0
		error += self.read_specs()
		error += self.read_status()
		error += self.read_config()
		error += self.build_channels()

		if error == 0:
			return 0
		else:
			return 1

	def read_specs(self):
		""" valide aussi l'adresse"""
		logger.debug("CALL")
		error = 0
		self.specs['timestamp'] = datetime.now().strftime(date_format)
		# CPU
		logger.debug("Reading firmware version and SN")
		reply = self.coms("GI")
		if reply is not None:
			self.specs['address'] = self.add
			self.specs['firmware-version'] = reply.split(" ")[0]
			self.specs['SN'] = reply.split(" ")[1]
			self.firmvers = self.specs['firmware-version'].lower()
			self.firmvers = self.firmvers.replace("v","")
			self.firmvers = tuple(map(int,(self.firmvers.split("."))))
		else:
			error = 1
			logger.error("Read SN/firmware version failed")
			
		# PCB Led
		# Lecture du type et numéro de série
		logger.debug("Reading PCB LED type and SN")
		reply = self.coms("GL")
		#@! implémenter l'absence de carte comme exception !
		if reply is not None:
			try:
				rspl = reply.split(" ")
				self.specs['pcb-led'] = {
				0: {'type': int(rspl[0]), 'SN': str(rspl[1])},
				1: {'type': int(rspl[2]), 'SN': str(rspl[3])},
				}
			except:
				error = 1
				logger.error("Read type/SN of PCB LED failed")
		else:
			error = 1
			logger.error("Read type/SN of PCB LED failed")
		# Lecture du descriptif détaillé
		if len(self.specs['pcb-led'])>0:
			logger.debug("Reading PCB LED description")
			for k,v in self.specs['pcb-led'].items():
				v['desc'] = {}
				reply = self.coms(cmd="GB", arg=k)
				if reply is not None:
					reply = reply.split(" ")[1]
					# PCB
					v['desc']['crc'], reply = decrypt_led(reply, 16, "hex")
					v['desc']['type'], reply = decrypt_led(reply, 16, "int")
					v['desc']['SN'], reply = decrypt_led(reply, 32, "hex")
					# Canaux
					v['desc']['channels'] = {}
					i = 0
					while reply != "":
						ch = {}
						ipeek, reply = decrypt_led(reply, 8, "int")
						ch['i_peek'] = ipeek*5 # mA
						ch['manuf'], reply = decrypt_led(reply, 8, "text")
						col, reply = decrypt_led(reply, 16, "text")
						ch['col'] = col[::-1]
						ch['wl'], reply = decrypt_led(reply, 16, "int")
						ch['code_col'] = str(ch['col'])+"_"+str(ch['wl'])
						v['desc']['channels'][i] = ch
						i += 1
		return error

	def get_specs(self, what=[], update=False, timestamp=True):
		logger.debug("CALL")
		specs = convert_tolistof(data=what, type_out=str, none_omit=True)
		if update:
			self.read_specs()
		out = {}
		if len(specs) == 0:
			out = self.specs
		else:
			out = {}
			for p in specs:
				try:
					out[p] = self.specs[p]
				except:
					out[p] = None
		if timestamp:
			out['timestamp'] = self.specs['timestamp']

		return out

	def read_status(self):
		logger.debug("CALL")
		error = 0
		self.status['timestamp'] = datetime.now().strftime(date_format)
		reply = self.coms("GE")
		if reply is not None:
			rspl = reply.split(" ")
			# Config
			self.status['config'] = rspl[0]
			# Drivers
			rspl[1] = rspl[1].replace("O", "0")
			rspl[1] = list(bin(int(rspl[1], 16))[2:])
			rspl[1] = list(map(int, rspl[1]))
			self.status['drivers'] = {
			'global' : None,
			'descr' : {}
			}
			try:
				if sum(rspl[1]) == len(rspl[1]):
					self.status['drivers']['global'] = "OK"
				else:
					self.status['drivers']['global'] = "ERR"
			except:
				self.status['drivers']['global'] = None
				error = 1
				logger.error("Compute drivers global status failed")
			i = 0
			for s in rspl[1]:
				try:
					self.status['drivers']['descr'][i] = int(s)
				except:
					self.status['drivers']['descr'][i] = None
					error = 1
					logger.error("Read of driver "+str(i)+"'status failed")
				i += 1
		else:
			self.status['config'] = None
			self.status['drivers'] = {'global': None, 'descr' : None}
			error = 1
			logger.error("Status read failed")

		return error

	def get_status(self, what=[], update=False, timestamp=True):
		logger.debug("CALL")
		what = convert_tolistof(data=what, type_out=str, none_omit=True)
		if update:
			self.read_status()
		out = {}
		if len(what) == 0:
			out = self.status
		else:
			out = {}
			for p in what:
				try:
					out[p] = self.status[p]
				except:
					out[p] = None
		if timestamp:
			out['timestamp'] = self.status['timestamp']

		return out

	def read_temp(self):
		logger.debug("CALL")
		error = 0
		self.temp = {
		'cpu': None,
		'pcb-led': None,
		'timestamp': datetime.now().strftime(date_format)
		}
		reply = self.coms("GT")
		if reply is not None:
			rslp = reply.split(" ")
			if len(rslp) > 0:
				self.temp['cpu'] = rslp[0]
				self.temp['pcb-led'] = {}
				i = 0
				for t in rslp[1:]:
					try:
						self.temp['pcb-led'][i] = float(t)
					except:
						self.temp['pcb-led'][i] = None
						error = 1
						logger.error("Unable to convert temperature to float")
					i += 1
			else:
				error = 1
				logger.error("Temperatures string is empty")
		else:
			error = 1
			logger.error("Temperatures read failed")

		return error

	def get_temp(self, what=[], update=False, timestamp=True):
		logger.debug("CALL")
		what = convert_tolistof(data=what, type_out=str, none_omit=True)
		if update:
			self.read_temp()
		out = {}
		if len(what) == 0:
			out = self.temp
		else:
			out = {}
			for p in what:
				try:
					out[p] = self.temp[p]
				except:
					out[p] = None
		
		if timestamp:
			out['timestamp'] = self.temp['timestamp']

		return out

	def read_config(self):
		logger.debug("CALL")
		error = 0
		self.config['timestamp'] = datetime.now().strftime(date_format)
		# Lecture des paramètres globaux
		logger.debug("Reading general parameters")
		reply = self.coms("GF")
		if reply is not None:
			reply = reply.replace("O", "0") # erreur dans code C du luminaire
			rspl = list((bin(int(reply, 16))[2:]))
			rspl = list(map(int, rspl))
			rspl.reverse()
			template = [0,0,0,0]
			for i in range(0,len(template)):
				try:
					template[i] = rspl[i]
				except IndexError:
					template[i] = 0
			self.config['power'] = template[0]
			self.config['master'] = template[1]
			self.config['overdrive'] = int(str(template[2])+str(template[3]), 2)
		else:
			error = 1
			logger.error("Parameters read failed")

		# Lecture de la fréquence du PWM
		logger.debug("Reading PWM frequency")
		reply = self.coms("GS")
		if reply is not None:
			try:
				self.config['freq'] = float(reply)/10
			except:
				error = 1
				logger.error("PWM frequency can't be converted to float")
		else:
			error = 1
			logger.error("PWM frequency read failed")

		return error

	def get_config(self, what=[], update=False, timestamp=True):
		logger.debug("CALL")
		what = convert_tolistof(data=what, type_out=str, none_omit=True)
		
		if update:
			self.read_config()
		
		out = {}
		if len(what) == 0:
			out = self.config
		else:
			out = {}
			for p in what:
				try:
					out[p] = self.config[p]
				except:
					out[p] = None
		
		if timestamp:
			out['timestamp'] = self.config['timestamp']

		return out

	def set_config(self, config={}):
		logger.debug("CALL")
		if type(config) is not dict:
			logger.error("Argument 'config' must be of type 'dict'")
			return 1
		# Mise à jour de la config avant modification
		self.read_config()
		config_new = copy.deepcopy(self.config)
		# Encodage
		error = 0
		for k,v in config.items():
			try:
				float(v)
				int(v)
			except:
				error += 1
				logger.error("Value for '"+str(k)+"' can't be converted to int or float")
				continue
			if k == 'master':
				if int(v) not in [0,1]:
					error += 1
					logger.error("Value for master not valid")
				else:
					config_new['master'] = int(v)
			elif k == 'power':
				if int(v) not in [0,1]:
					error += 1
					logger.error("Value for power not valid")
				else:
					config_new['power'] = int(v)
			elif k == 'overdrive':
				if self.firmvers < (1,4):
					logger.warning("Overdrive not available with version "\
						+str(self.specs['firmware-version']))
					config_new['overdrive'] = 0
				else:
					if int(v) not in [0,1,2]:
						error += 1
						logger.error("Value for overdrive not valid")
					else:
						config_new['overdrive'] = int(v)
			elif k == "freq":
				config_new['freq'] = float(v)
			else:
				logger.warning("Parameters '"+str(k)+"' not recognized")
		try:
			bitmask = str(bin(config_new['overdrive'])[2:]).zfill(2)
			bitmask += str(config_new['master'])
			bitmask += str(config_new['power'])
			bitmask = int(bitmask, 2)
		except:
			error += 1
			bitmask = None
			logger.error("Unable to format config bitmask")
		# Envoie de la config
		logger.debug("Send config bitmask")
		reply = self.coms(cmd="SF", arg=bitmask)
		if reply == "ACK":
			logger.debug("Config successfully modified")
		else:
			error += 1
			logger.error("Config write failed")
		# Fréquence
		reply = self.coms(cmd="SS", arg=config_new['freq']*10)
		if reply == "ACK":
			logger.debug("Frequency successfully modified")
		elif reply == "NAK 4":
			error += 1
			logger.error("Frequency "+str(config_new['freq'])+"Hz not permitted")
		else:
			error += 1
			logger.error("Frequency write failed")

		if error == 0:
			return 0
		else:
			return 1
	
	def build_channels(self):
		logger.debug("CALL")

		try:
			if len(self.specs['pcb-led'][0]['desc']["channels"].keys()) > 0:
				pass
			else:
				logger.error("No channels description found")
				return 1
		except:
			logger.error("No channels description found")
			return 1

		for k,v in self.specs['pcb-led'][0]['desc']["channels"].items():
			c = Channel(channel_id=k, address=self.add,\
				com=self.com, specs=v, spot_config=self.config)
			self.channels[k] = c
			self.available_color.append(v['code_col'])
			try:
				self.channels_bycolor[v['code_col']].append(c)
			except KeyError:
				self.channels_bycolor[v['code_col']] = [c]
		self.available_color = list(set(self.available_color))
		
		return 0

	def get_available_colors(self):
		logger.debug("CALL")
		return self.available_color

	def set_channels(self, channels, intensity, pwm_start=0, pwm_stop=1):
		logger.debug("CALL")
		channels = convert_tolistof(data=channels, type_out=int, none_omit=True)

		if len(channels) == 0:
			return 1
		if min(channels) < 0:
			channels = list(self.channels.keys())
		channels = list(set(channels) & set(list(self.channels.keys())))
		
		error = 0
		for i in channels:
			error += self.channels[i].set_config(intensity=intensity, start=pwm_start, stop=pwm_stop)
		
		if error == 0:
			return 0
		else:
			return 1

	def set_colors(self, colors, intensity, pwm_start=0, pwm_stop=1):
		logger.debug("CALL")
		colors = convert_tolistof(data=colors, type_out=str, none_omit=True)
		if "*" in colors:
			colors = self.available_color
		colors = list(set(colors) & set(self.channels_bycolor.keys()))
		error = 0
		for c in colors:
			for ch in self.channels_bycolor[c]:
				error += ch.set_config(intensity=intensity, start=pwm_start, stop=pwm_stop)
		if error == 0:
			return 0
		else:
			return 1

	def shutdown(self, channels=None, colors ="*"):
		logger.debug("CALL")
		channels = convert_tolistof(data=channels, type_out=int, none_omit=True)
		colors = convert_tolistof(data=colors, type_out=str, none_omit=True)
		if len(channels) != 0:
			error = self.set_channels(channels=channels, intensity=0, pwm_start=0, pwm_stop=0)
		else:
			error = self.set_colors(colors=colors, intensity=0, pwm_start=0, pwm_stop=0)
		return error

	def get_channels_config(self, channels=-1, what=[], update=False, timestamp=True):
		logger.debug("CALL")
		channels = convert_tolistof(data=channels, type_out=int, none_omit=True)
		what = convert_tolistof(data=what, type_out=str, none_omit=True)

		out = {}
		if len(channels) == 0:
			return out
		if min(channels) < 0:
			channels = list(self.channels.keys())
		for i in channels:
			try:
				out[i] = self.channels[i].get_config(what=what, update=update, timestamp=timestamp)
			except:
				out[i] = None

		return out

	def write(self):
		logger.debug("CALL")
		reply = self.coms("SV")
		if reply == "ACK":
			return 0
		else:
			return 1

	def restart(self):
		logger.debug("CALL")
		reply = self.coms("RST")
		if reply == "ACK":
			return 0
		else:
			return 1

# ----------------------------------------------------------------------------
class Channel():
	def __init__(self, channel_id, address, com, specs, spot_config):
		logger.debug("CALL")
		self.ch_id = channel_id
		self.add = address
		self.com = com
		self.specs = specs
		self.spot_config = spot_config
		self.config = {
		'current': None,
		'intensity': None,
		'pwm_start': None,
		'pwm_stop': None,
		'timestamp': None
		}

	def coms(self, cmd, arg=""):
		logger.debug("CALL")
		reply = self.com.send(cmd=cmd, prefix=[self.add, self.ch_id], arg=arg, checksum=True)
		#print(reply)
		if str(reply['reply']).startswith("NAK"):
			logger.warning("Com. received code "+str(reply['reply']))
		if reply['error'] == True:
			return None
		else:
			return reply['reply']

	def read_config(self):
		logger.debug("CALL")
		error = 0
		self.config['timestamp'] = datetime.now().strftime(date_format)
		reply = self.coms("GC")
		if reply is not None:
			rspl = reply.split(" ")
			if len(rspl) == 4:
				self.config['current'] = int(rspl[1])*(1140/200)
				self.config['intensity'] = 100*int(rspl[1])*(1140/200)/self.specs['i_peek']
				self.config['pwm_start'] = int(rspl[2])/200
				self.config['pwm_stop'] = int(rspl[3])/200
			else:
				error = 1
				logger.error("Read configuration of channel "+\
					str(self.add)+":"+str(self.ch_id)+" failed")
		else:
			error = 1
			logger.error("Read configuration of channel "+\
					str(self.add)+":"+str(self.ch_id)+" failed")
		return error

	def get_config(self, what=[], update=False, timestamp=True):
		logger.debug("CALL")
		what = convert_tolistof(data=what, type_out=str, none_omit=True)
		if update:
			self.read_config()

		out = {}
		if len(what) == 0:
			out = self.config
		else:
			out = {}
			for p in what:
				try:
					out[p] = self.config[p]
				except:
					out[p] = None
		
		if timestamp:
			out['timestamp'] = self.config['timestamp']

		return out

	def set_config(self, intensity, start=0, stop=1):
		logger.debug("CALL")
		# Vérification
		try:
			intensity = float(intensity)
			start = float(start)
			stop = float(stop)
		except:
			logger.error("Arguments must be floats")
			return 1
		# PWM
		if start < 0 or start >1:
			start = 0
			logger.warning("PWM start point is not valid and is replaced by '0'")
		if stop < 0 or stop > 1:
			stop = 1
			logger.warning("PWM stop point is not valid and is replaced by '1'")
		pwm_start = int(start*200)
		pwm_stop = int(stop*200)
		# Courant
		if start == stop or intensity < 0:
			current_step = int(0)
		else:
			current_max = self.specs['i_peek'] # mA
			current_max = 0.494*current_max/abs(stop-start) # par défaut 50% du courant pic
			current = intensity*current_max/100
			current_step = int(200*(current/1140))
			if current_step > 200:
				current_step = int(200)

		# Envoi
		reply = self.coms("SC", arg=[current_step, pwm_start, pwm_stop])
		if reply == "ACK":
			return 0
		else:
			return 1

	def shutdown(self):
		logger.debug("CALL")
		reply = self.coms("SC", arg=[0,0,0])
		if reply == "ACK":
			return 0
		else:
			return 1
