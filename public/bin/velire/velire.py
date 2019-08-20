#!/usr/bin/env python3
# -*- coding: utf-8 -*-

'''
------------------------------------------------------------------------------
MODULE VELIRE: CONTROLE DU SPOT VELIRE
------------------------------------------------------------------------------
Anthony Fratamico
Laboratoire de Physiologie Végétale
Institut de Botanique, Université de Liège
18 septembre 2018
------------------------------------------------------------------------------
'''

# ----------------------------------------------------------------------------
# DEPENDANCES

import serial
import os
import datetime
import codecs
from time import sleep

# ----------------------------------------------------------------------------
# VARIABLES

release = "0.0 [dev, 2018-09-18]"

spot_cmd_dict = {

# SF
"set_function" :
{"cmd": "SF",
"doc":
'''
Cette commande permet de spécifier deux fonctions du SPOT.
Un seul argument est utilisé fixant un masque binaire pour les deux fonctions.

Bit 0: Si ce bit est à 1, les drivers de LED sont activés, sino ils sont en shutdown.
Bit 1: Si ce bit est à 1, le SPOT concerné obtient le rôle MASTER.
(Un seul et un seul SPOT du reseau doit avoir le rôle de MASTER).
''',
"last_update" : "2018-09-19"
},

# GF
"get_function":
{"cmd": "GF",
"doc":
'''
Cette commande permet de relire la valeur du bitmask des fonctions.
Réponse: "#GF 0x00000003*XX<CR>"
''',
"last_update" : "2018-09-19"
},

# SS
"set_frequence":
{"cmd": "SS",
"doc":
'''
Cette commande permet de spécifier la fréquence du PWM difital entre 0,1 et 10 kHz.
Un seul argument est utilisé donnant la fréquence désirée en 1/10ème de Hz.
Un nombre limité de fréquence est autorisé. En cas d'impossibilité, le SPOT répondra par un NAK4.
Note: Tous les SPOT's du réseau doivent être programmé avec la même valeur.
''',
"last_update" : "2018-09-19"
},

# GS
"get_frequence":
{"cmd": "GS",
"doc": None,
"last_update" : "2018-09-19"
},

# GT
"get_temperature":
{"cmd": "GT",
"doc": None,
"last_update" : "2018-09-19"
},

# GL
"get_led":
{"cmd": "GB",
"doc": None,
"last_update" : "2018-09-19"
},

# SC
"set_channel":
{"cmd": "SC",
"doc": None,
"last_update" : "2018-09-20"
},

# GC
"get_channel":
{"cmd": "GC",
"doc": None,
"last_update" : "2018-10-03"
},

# GI
"get_cpu":
{"cmd": "GI",
"doc": None,
"last_update" : "2018-10-03"
},

# GE
"get_status":
{"cmd": "GE",
"doc": None,
"last_update" : "2018-10-04"
},

# SM
"set_master":
{"cmd": "SM",
"doc": None,
"last_update" : "2018-11-28"
},

# SP
"set_state":
{"cmd": "SP",
"doc": None,
"last_update" : "2018-11-28"
},
# SV
"save_conf":
{"cmd": "SV",
"doc": None,
"last_update" : "2018-11-28"
}
}

# ----------------------------------------------------------------------------
# FONCTIONS
verbosity = 10
def verbose(msg, level):
	global verbosity
	if level <= verbosity:
		print(msg)

def serial_dialog(ser, cmd, add, arg="", checksum=True, time_for_reply=15):
	""" Envoie une commande sur ser et renvoi la réponse

	ser: Objet serial
	cmd: commande du spot (cf. dictionnaire des commandes)
	add: addresse du spot
	arg: les arguments de la commande (cf. dictionnaire des commandes)
	checksum: vérifie la communication (True/False)
	time_for_reply: temps d'attente max. pour la réponse (s). Defaut: 15s.
	"""
	verbose("CALLFCT: serial_dialog", 10)
	def compute_checksum(string):
		""" Calcul la somme de contôle

		Calcule le reste de la division par 256 (2^8) de la somme binaire d'une chaine de caractère
		(voir doc). Reste de la somme car checksum codée en 8bits par le driver.
		Réponse de type integer
		"""

		checksum = 0
		for x in list(string):
			checksum = checksum + ord(x)
		return checksum % 256

	# Dictionnaire pour la sortie
	reply_dict = {'raw': None, 'cmd': None, 'checksum': None, 'reply': None, 'log': None, 'error': True}

	# Vérifie que le port est ouvert
	ser_state = ser.isOpen()
	if ser_state == False:
		try:
			ser.open()
		except:
			reply_dict['log'] = "Unable to open serial port"
			verbose(reply_dict['log'], 1)
			return reply_dict

	# Préparation de la commande balisée
	cmdbal = str("@"+str(cmd)+" "+str(add)+" "+str(arg)+"\r")
	if checksum == True:
		cmdbal = cmdbal[:-1]+"*"+str(compute_checksum(cmdbal))+"\r"
	reply_dict['cmd'] = cmdbal

	# Envoie de la commande
	verbose("Sending to serial: "+cmdbal, 10) # Pour le deboggage
	ser.write(cmdbal.encode()) # encodage ascii nécessaire

	# Écoute de la réponse
	reply = ""
	Loop = True
	x = None
	while Loop == True:
		for x in ser.read().decode():
			if x == "\r":
				Loop = False
				break
			else:
				if x != "\n": # semble arriver quand on ne ferme pas le port entre deux comunications
					reply=reply+str(x)
		if x == None: # le port série n'a rien répondu
			Loop = False
	ser.flushInput() # flush, utile si timeout dépassé
	ser.flushOutput()

	# Sauvegarde de la réponse brute
	reply_dict['raw'] = reply
	verbose("Received from serial: "+reply, 10)

	# Vérification de la réponse
	if reply == "":
		verbose(msg="ERROR: reply is empty", level=9)
		reply_dict['log'] = "reply was empty"
		return reply_dict

	# Type de réponse
	if (reply[0] == "#") and (cmd in reply) and ("*" in reply): # réponse structurée
		reply_dict['reply'] = reply.split("*")[0].replace("#"+cmd+" ", "") # supprime l'entête
		reply_dict['checksum'] = compute_checksum(reply.split("*")[0])
		if reply_dict['checksum'] == int(reply.split("*")[1], 16):
			reply_dict['error'] = False
		else:
			reply_dict['log'] = "wrong checksum"
	else:
		if reply == "ACK" or reply == "NACK":
			reply_dict['reply'] = reply
			reply_dict['error'] = False
		else:
			reply_dict['log'] = "wrong reply structure"

	if ser_state == False: # remet dans l'état initial
		ser.close() 
	return reply_dict

# ----------------------------------------------------------------------------
# CLASSES

class Channel():

	def __init__(self):
		""" Déclare les variables de base

		"""
		self.ser = None
		self.ch_dict = {'address': None,
						'id': None,
						'type': None,
						'wl': None,
						'color': None,
						'max': None,
						'manuf': None,
						'intensity': None,
						'unit': "%",
						'pwm_start': None,
						'pwm_stop': None,
						'last_request': None,
						'status':{}
						}

	def pass_serial(self, ser):
		""" Transmet le canal du port série du spot préalablement défini

		"""
		self.ser = ser
		return

	def get_ledinfo(self, param):
		""" Renvoi la valeur du paramètre demandé

		"""
		if param == "all":
			return self.ch_dict
		if param in self.ch_dict.keys():
			return self.ch_dict[param]
		else:
			return None

	def get_config(self):
		""" Renvoi la valeur programmée du cana

		"""
		reply = serial_dialog(self.ser, spot_cmd_dict["get_channel"]["cmd"], self.ch_dict['address'], arg = str(self.ch_dict['id']))
		if reply['error'] == False:
			reply_split = reply['reply'].split(" ")
			out = {'channel': reply_split[0], 'intensity': int(reply_split[1])/2, 'pwm_start': int(reply_split[2])/200, 'pwm_stop': int(reply_split[3])/200} # intensité en % et PWM dans [0,1]
			return out
		else:
			return None

	def set_config(self, intensity, unit, start, stop):
		""" Envoi la configuration demandée au canal

		intensity: l'intensité du courant
		unit: unité pour l'intensité du courant (défaut: % du max)
		start: moment d'allumage (fraction de la phase du PWM)
		stop: moment d'extinction (fraction de la phase du PWM)
		"""

		# Conversion de 'intensity' en fonction de l'unité
		self.ch_dict['last_request'] = {'intensity': intensity, 'unit': unit, 'pwm_start': start, 'pwm_stop': stop}
		if self.ch_dict['max'] == None:
			#verbose("Empty channel")
			return None
		if unit == "%":
			if (float(intensity) < 0) or (float(intensity) > 100):
				verbose("ERROR: intensity out of range (0-100%)", 3)
				return None
			intensity = int(intensity*2)
			if intensity > self.ch_dict['max']:
				verbose("ERROR: intensity greather than current max", 3)
				return None
		else:
			verbose("ERROR: Undefined unit", 3)
			return None

		# Vérification du timing d'allumage
		if (float(start) < 0 or float(start) > 1) or (float(stop) < 0 or float(stop) > 1):
			verbose("ERROR: time out of range (0-1)", 3)
			return None
		start = int(start * 200)
		stop = int(stop *200)
		if start > stop:
			verbose("ERROR: start define after stop", 3)
			return None

		# Envoi de la commande
		reply_raw = serial_dialog(self.ser, spot_cmd_dict["set_channel"]["cmd"], self.ch_dict['address'], arg = str(self.ch_dict['id'])+" "+str(intensity)+" "+str(start)+" "+str(stop))['reply']
		config = self.get_config()
		self.ch_dict['intensity'] = config['intensity']
		self.ch_dict['pwm_start'] = config['pwm_start']
		self.ch_dict['pwm_stop'] = config['pwm_stop']
		if reply_raw == "ACK":
			return 0
		else:
			return reply_raw

	def shutdown(self):
		""" Racourci pour étiendre le canal

		"""
		self.set_config(0, "%", 0, 1)

	def new(self, ser, spot_add, id, type, wl, max, manuf):
		""" Défini les paramètres du canal

		"""
		self.ser =  ser
		self.ch_dict['address'] = spot_add
		self.ch_dict['id'] = id
		self.ch_dict['type'] = type
		self.ch_dict['wl'] = wl
		self.ch_dict['max'] = max
		self.ch_dict['manuf'] = manuf
		self.ch_dict['color'] = str(type)+"_"+str(wl)
		config = self.get_config()
		self.ch_dict['intensity'] = config['intensity']
		self.ch_dict['pwm_start'] = config['pwm_start']
		self.ch_dict['pwm_stop'] = config['pwm_stop']
		self.ch_dict['last_request'] = None


class Spot():
	""" Définit un spot sur le réseau via son numéro de série

	Charge les objets channels associés
	Differentes fonctions de contrôle d'un spot
	"""

	def __init__(self):
		""" Définit la liste des variables de base.

		Valeur par défaut: None
		"""
		self.ser = None
		self.address = None
		self.grid_id = None
		self.group = None # not used
		self.network = None # not used
		self.pcb = {}
		self.cpu = None
		self.channels = None
		self.channels_color = {}
		self.available_colors = []
		self.symmetry = None
		# Recherche des fréquences (en Hz) disponibles (selon code source du driver, com. Pierre Jenard 17/09/18 10:41)
		self.available_freq = []
		'''
		for i in range(1, 100001):
			periods = [9600,6000,4800,3200]
			for p in periods:
				timerF = p*i/10
				psc = int(48000000 / timerF)
				if timerF <= 48000000 and int(psc*timerF) == 48000000:
					self.available_freq.append(i/10)
		self.available_freq = sorted(list(set(self.available_freq)))
		'''
		self.frequency = None
		self.box = None
		self.errors = {}

	def set_address(self, address):
		""" Défini l'adresse du spot sur le réseau

		L'adresse est fixée par le n° de série du spot
		et est comprise en 1 et 1000000.
		"""

		try:
			int(address)
		except:
			verbose("Spot address must be an integer (1-1000000)", 8)
			return
		
		if int(address) < 1 | int(address) > 1000000:
			verbose("Spot address must be comprised between 1 and 1000000", 8)
			return

		self.address = str(int(address)) # converti en str pour usage futur
		return self.address

	def get_address(self):
		""" Renvoi l'adresse du spot sur le réseau

		"""
		return int(self.address)

	def get_temp(self):
		""" Renvoi la température du CPU du driver et des cartes LED en °C

		"""
		temp_dict = {"cpu": None, "led_pcb_0": None, "led_pcb_1": None, "unit": "C"}
		reply = serial_dialog(self.ser, spot_cmd_dict["get_temperature"]["cmd"], self.address)
		if reply['error'] == False:
			temp = reply['reply'].split(" ")
			temp_dict['cpu'] = float(temp[0])
			temp_dict["led_pcb_0"] = float(temp[1])
			temp_dict["led_pcb_1"] = float(temp[2])
			
		return temp_dict

	def get_cpuinfo(self):
		""" Renvoie la version du firmware et le numéro de série du CPU

		"""
		reply = serial_dialog(self.ser, spot_cmd_dict["get_cpu"]["cmd"], self.address)
		if reply['error'] == False:
			cpu = {'firmware_version': reply['reply'].split(" ")[0], 'serial':reply['reply'].split(" ")[1]}
		else:
			cpu = None
		return cpu

	def get_pcbledinfo(self, pcb=[0,1]):
		""" Lit les informations dans la mémoire de chaque PCB Led

		Revoi une liste structurée (dictionaire)
		"""
		def decode_pcbledinfo(string, pcb):
			""" Decode la chaine de caractères codée en hexadécimale

			Si données de plus de 8bits, inverser la lecture (par deux caractères)
			car LSB first
			"""
			
			# Fonction qui regroupe les "bits" et les décode
			def substring_decode(input, start, length, reverse, coding):
				tmp = input[start:(start+length)]
				if reverse == True: # pour les données de plus de 8 bits envoyées LSB first (voir doc)
					tmp.reverse()
				tmp = "".join(tmp)
				if coding == "text":
					tmp = bytearray.fromhex(tmp).decode()
				if coding == "int":
					tmp = int(tmp, 16)
				if coding == "hex":
					tmp = "0x"+tmp
				return tmp

			# Dictionnaire pour la sortie
			info_dict = {}

			# Découpe de la chaine par deux caractères
			string_splitted = [string[i:i+2] for i in range(0, len(string), 2)]
			
			# Information sur la carte LED
			#info_dict["raw_info_pcb_"+str(pcb)] = string # pour debbogage
			info_dict["pcb"]={}
			info_dict["pcb"]['n'] = pcb
			info_dict["pcb"]['crc'] = substring_decode(input=string_splitted, start=0, length=2, reverse=True, coding="hex")
			info_dict["pcb"]['type'] = substring_decode(input=string_splitted, start=2, length=2, reverse=True, coding="int")
			info_dict["pcb"]['serial'] = substring_decode(input=string_splitted, start=4, length=4, reverse=True, coding="hex")

			# Informations des canaux
			info_len = 6 # nb bytes par canal
			nb_channels = int((len(string_splitted) - 8)/info_len) # recherche du nombre de canaux en fonction de la longueur de la chaine d'info (8 pour l'en-tête)
			info_dict['channels']={}
			for i in range(0,nb_channels):
				info_dict['channels'][i]={}
				info_dict['channels'][i]['channel'] = i
				info_dict['channels'][i]['i_peek'] = substring_decode(input=string_splitted, start=8+i*info_len, length=1, reverse=False, coding="int")
				info_dict['channels'][i]['manuf'] = substring_decode(input=string_splitted, start=8+i*info_len+1, length=1, reverse=False, coding="text")
				info_dict['channels'][i]['led_type'] = substring_decode(input=string_splitted, start=8+i*info_len+2, length=2, reverse=False, coding="text")
				info_dict['channels'][i]['wave_length'] = substring_decode(input=string_splitted, start=8+i*info_len+4, length=2, reverse=True, coding="int")
			
			# Sortie
			return info_dict

		# Lecture des informations de chaque carte (pcb)
		reply={}
		for i in pcb: # voir valeur par défaut dans les arguments de la fonction
			reply_tmp = serial_dialog(self.ser, spot_cmd_dict["get_led"]["cmd"], self.address, str(i))
			if reply_tmp['error'] == False:
				reply[i] = decode_pcbledinfo(string=reply_tmp['reply'].split(" ")[1], pcb=i) # décodage de la chaine
			else:
				reply[i] = None

		return reply

	def load_channels(self, pcb=[0,1]):
		""" Crée une liste d'objets channels en fonction des informations lues dans la mémoire des PCB LED

		"""
		spot_info = self.get_pcbledinfo()
		self.channels = []
		# Configuration de chaque canal
		for i in range(0, len(spot_info[0]["channels"])):
			for key, value in spot_info[0]["channels"][i].items():
				self.symmetry = True
				for j in range(0, len(spot_info)): # Vérifie la symétrie
					if value != spot_info[j]["channels"][i][key]:
						self.symmetry = False
			if self.symmetry == True:
				value = spot_info[0]["channels"][i]
				c = Channel()
				c.new(	ser= self.ser, spot_add = self.address, id = i,
						type = value["led_type"], wl = value["wave_length"],
						max = value["i_peek"], manuf = value["manuf"])
				self.channels.append(c)
				if c.get_ledinfo("color") not in self.channels_color.keys():
					self.channels_color[c.get_ledinfo("color")] = [c.get_ledinfo("id")]
				else:
					self.channels_color[c.get_ledinfo("color")].append(c.get_ledinfo("id"))
				# Sauvegarde de la liste des couleurs disponibes
				self.available_colors = list(sorted(set(self.channels_color.keys())))
			else:
				c = Channel()
				c.ch_dict['id'] = i # force pour un canal vide
				self.channels.append(c) # empty channel
		return

	def get_status(self):
		""" Renvoi le status des 16 drivers LED

		"""
		reply = serial_dialog(self.ser, spot_cmd_dict["get_status"]["cmd"], self.address)
		if reply['error'] == False:
			drivers_st = list(str(bin(int(reply['reply'].split(" ")[1][2:], 16)))[2:]) # Hexa décimal en binaire = 16bits, 1 par canal = status
			drivers_st_prod = 1
			drivers_st_dict = {}
			for i in range(0, len(drivers_st)):
				drivers_st_dict[i] = int(drivers_st[i])
				drivers_st_prod = drivers_st_prod * int(drivers_st[i])
			status = {	'config': reply['reply'].split(" ")[0],
						'drivers':  {'global': drivers_st_prod, 'details': drivers_st_dict}
						}
		else:
			 status = {'config': None, 'drivers': None}

		return status			

	def get_freq(self):
		""" Renvoi la fréquence de l'horloge du spot

		"""
		reply = serial_dialog(self.ser, spot_cmd_dict["get_frequence"]["cmd"], self.address)
		if reply['error'] == False:
			return int(reply['reply'])/10 # en Hz (voir doc)
		else:
			return None

	def set_freq(self, freq):
		""" Assigne une fréquence pour le PWM

		"""
		self.errors["set_freq"] = True
		if freq not in self.available_freq:
			verbose("Frequency not available", 8)
			return
		reply = serial_dialog(self.ser, spot_cmd_dict["set_frequence"]["cmd"], self.address, freq*10) # itération par 1/10è de Hz (voir doc)
		self.frequency = self.get_freq()
		if reply['error'] == False and freq == self.frequency:
			self.errors["set_freq"] = False
		return self.frequency

	def set_channel(self, conf):
		""" Contrôle un canal

		"""
		#self.channels[int(conf["channel"])].pass_serial(self.ser)
		reply = self.channels[int(conf["channel"])].set_config(intensity=conf["intensity"], unit=conf["unit"], start=conf["start"], stop=conf["stop"])
		return reply

	def set_ms(self, master, state=0):
		""" Définit le rôle du spot: maître ou esclave

		"""
		if master == True:
			reply1 = serial_dialog(self.ser, spot_cmd_dict["set_master"]["cmd"], self.address, 1) # maître
		else:
			reply1 = serial_dialog(self.ser, spot_cmd_dict["set_master"]["cmd"], self.address, 0) #esclave

		reply2 = serial_dialog(self.ser, spot_cmd_dict["set_state"]["cmd"], self.address, state) # état du driver
		reply3 = serial_dialog(self.ser, spot_cmd_dict["save_conf"]["cmd"], self.address) # enregistre dans la mémoire du driver
		
		return [reply1, reply2, reply3]
	
	def set_state(self, state):
		""" Défini l'état des drivers

		"""
		reply = serial_dialog(self.ser, spot_cmd_dict["set_state"]["cmd"], self.address, state)
		return reply

	def shutdown(self):
		""" Eteind tous les canaux

		"""
		reply = []
		for c in self.channels:
			#c.pass_serial(self.ser)
			reply.append(c.shutdown())

	def set_bycolor(self, conf):
		""" Allume tout les canaux qui ont la même couleur

		"""
		if conf["colortype"] not in self.channels_color.keys():
			verbose("Color '"+conf["colortype"]+"' not available", 8)
			return -1

		error = 0
		for i in self.channels_color[conf["colortype"]]:
			conf["channel"] = i
			reply = self.set_channel(conf)
			if reply != 0:
				error += 1
		return error

	def get_info(self):
		""" Renvoi une liste détaillé du statut du spot

		"""
		infos = {}
		infos["firmware_version"] = self.cpu["firmware_version"]
		infos["serial"] = self.cpu["serial"]
		infos["frequency"] = {'value': self.frequency, 'unit' : "Hz"}
		infos["pcb"]=self.pcb
		infos['symmetry'] = self.symmetry
		infos["temperature"] = self.get_temp()
		infos['status'] = self.get_status()
		infos["channels"] = {}
		for c in self.channels:
			ledinfos = c.get_ledinfo("all")
			infos["channels"][ledinfos['id']] = ledinfos
			#infos["channels"][ledinfos['id']]['driver_status'] = infos['status']['drivers']['details'][ledinfos['id']]
		infos["library_version"] = release
		infos["available_colors"] = self.available_colors
		infos["available_frequencies"] = self.available_freq
		infos["address"] = self.address
		infos["id"] = self.grid_id
		infos["box"] = self.box
		return infos

	def set_box(self, box):
		""" Groupe associé

		"""
		self.box = box

	def get_function(self):
		reply = serial_dialog(self.ser, spot_cmd_dict["get_function"]["cmd"], self.address)
		return reply

	def new(self, address, ser, grid_id=None):
		""" Crée un objet à partir des fonctions de base


		"""
		# Adresse sur le réseau
		address_reply = self.set_address(address)
		if str(address_reply) != str(address):
			verbose("Spot not found at address "+str(address), 8)
			return None

		# Port série
		self.ser = ser

		# id sur le réseau
		self.grid_id = grid_id

	def activate(self):
		self.cpu = self.get_cpuinfo	()
		self.frequency = self.get_freq() # Fréquence de l'horloge
		ledinfo = self.get_pcbledinfo()
		for k,v in ledinfo.items():
			if v is not None:
				tmp = v
		for k in ledinfo.keys():
			self.pcb[str(k)] = tmp['pcb']
		self.load_channels() # Charge les canaux

	# def activate(self):
 #    	self.cpu = self.get_cpuinfo	()
 #    	self.frequency = self.get_freq() # Fréquence de l'horloge
 #        ledinfo = self.get_pcbledinfo()
 #        for k,v in ledinfo.items():
 #            if v is not None:
 #                tmp = v
 #        for k in ledinfo.keys():
 #            self.pcb[str(k)] = tmp['pcb']
 #        self.load_channels() # Charge les canaux

	def activate2(self, config, avcol):
		self.available_colors = avcol
		self.channels = []
		keys = []
		for k in config.keys():
			keys.append(int(k))
		keys = sorted(keys)
		#for k,v in config.items():
		for k in keys :
			k = str(k)
			v = config[k]
			c = Channel()
			c.ser = self.ser
			c.ch_dict = v
			if v['color'] not in self.channels_color.keys():
				self.channels_color[v['color']] = [v['id']]
			else:
				self.channels_color[v['color']].append(v['id'])
			self.channels.append(c)

class Grid():
	""" Définit le réseau de spots, caractérisé par un port série

	"""

	def __init__(self):
		""" Déclare les varables de bases

		"""
		self.ser = None
		self.spots_list = []
		self.available_colors = []
		self.available_freq = []
		self.freq = None
		self.boxes = []
		self.out_of_boxes=[]

	def set_serial(self, port, baudrate, timeout):
		""" Déclare les paramètres du port série

		port: par exemple /dev/ttyUSB0
		baudrate: vitesse de communication, par défaut 115200
		"""

		# Vérifie la présence du port
		try:
			f = open(port, "r")
		except FileNotFoundError:
			verbose("Port '"+port+"' not found", 8)
			return

		# Vérifie le baudrate
		try:
			int(baudrate)
		except ValueError:
			verbose("Baudrate must be an integer", 8)
			return

		# Tente de configurer le port série + sauvegarde 
		try:
			self.ser = serial.Serial(port, int(baudrate), timeout=float(timeout))
		except:
			verbose("Unable to set serial port on '"+port+"'", 8)
			return

		# Fermeture du port
		try:
			self.ser.close()
		except:
			verbose("Unable to close port after initialization", 8)
			return

		return self.ser

	def get_serial(self):
		""" Renvoi le port série

		"""
		return self.ser

	def open(self):
		""" Ouvre le port série préfini avec set_serial
		
		"""

		# Vérifie que le port a été défini
		if self.ser == None:
			verbose("Serial port not define. Use set_serial first.", 8)
			return False

		# Ouverture du port
		if not self.ser.isOpen():
			try:
				self.ser.open()
			except:
				verbose("Unable to open serial port", 8)
				return False
		return self.ser.isOpen()

	def close(self):
		""" Ferme le port série ouvert avec open_serial
		
		"""

		# Vérifie que le port a été défini
		if self.ser == None:
			verbose("Serial port not define. Use set_serial first.", 8)
			return

		# Configuration le port
		if not self.ser.isOpen():
			verbose("Serial port already closed", 8)
			return

		try:
			self.ser.close()
		except:
			verbose("Unable to close serial port", 8)
			return

	def find_spot(self):
		tm_save = self.ser.timeout
		self.ser.timeout = 0.1
		result = {"found":[], "not_found":[]}
		for s in self.spots_list:
			reply = s.get_function()
			if reply["error"] == False:
				result['found'].append(int(s.address))
			else:
				result['not_found'].append(int(s.address))
		self.ser.timeout = tm_save
		return result

	def add_spot(self, address, grid_id):
		""" Ajoute un spot à la liste du réseau

		address: l'adresse du spot sur le réseau (type integer)
		"""
		if type(address) is int:
			s = Spot()
			s.new(ser = self.ser, address = address, grid_id = grid_id)
			self.spots_list.append(s) # ajoute le spot à la liste
			'''
			self.available_colors = sorted(list(set(self.available_colors + s.available_colors))) # ajoute les couleurs disponibles, et élimine les doublons
			self.available_freq = sorted(list(set(self.available_freq + s.available_freq))) # ajoute les fréquences disponibles, et élimine les doublons
			'''
		else:
			verbose("Invalid spot address '"+str(address)+"'", 8)

	def get_spot(self, value, target="id"):
		""" retourne un objet spot pour le controler indépendament

		"""
		if target == "id":
			return self.spots_list[value]
		if target == "add":
			for s in self.spots_list:
				if str(s.address) == str(value):
					return s

	def set_freq(self, freq):
		""" Défini la fréquence du PWM de tous les spots
		
		freq: fréquence en Hz
		"""
		reply_freq = []
		for s in self.spots_list:
			s.set_freq(freq = freq)
			reply_freq.append(s.get_freq())

		reply_freq = set(reply_freq)
		if len(reply_freq) == 1:
			self.freq = reply_freq
		else:
			self.freq = None

	def set_bycolor(self, conf, box = None):
		""" Allume tout les canaux qui ont la même couleur

		"""
		if box == None: # tout le réseau
			spots = self.spots_list
		else:
			spots = self.boxes[box]
		error = 0
		for s in spots:
			if conf["colortype"] in s.available_colors:
				reply = s.set_bycolor(conf)
				if reply > 0:
					error += 1
		return error

	def set_state(self, state, box=None):
		if box == None: # tout le réseau
			spots = self.spots_list
		else:
			spots = self.boxes[box]
		error = 0
		for s in spots:
			s.set_state(state)
		return error

	def shutdown(self, box = None):
		""" Mets tous les spots en shutdown

		spots: liste des id des spots, si 'None': éteind tout
		"""
		if box == None: # éteind tout
			for s in self.spots_list:
				s.shutdown()
			return
		if box in self.boxes.keys(): 
			for s in self.boxes[box]:
				s.shutdown()
		else:
			verbose("Undefined box '"+str(box)+"'", 8)	

	def new_boxes(self, n):
		""" Crèe n groupe

		n: nombre de groupe (max = nombre de spot sur la grille)
		"""
		if type(n) is not int:
			verbose("'n' must be an integer", 8)
			return
		if n > len(self.spots_list):
			verbose("Number of boxes greather than number of available spots", 8)
			return
		self.boxes = {}
		self.out_of_boxes=[]
		for i in range(0, n):
			self.boxes[i] = []
		for s in self.spots_list:
			self.out_of_boxes.append(s)
			s.set_box(None)

	def add_spots_to_box(self, spots, box):
		""" Lie les spots à un groupe

		"""
		if box not in range(0, len(self.boxes)):
			verbose("Undefine box '"+str(box)+"'", 8)
			return
		for i in spots:
			if i not in range(0, len(self.spots_list)):
				verbose("Undefine spot '"+str(i)+"'", 8)
				continue
			if self.spots_list[i] not in self.boxes[box]:
				# Supprime de l'ancien groupe
				if self.spots_list[i].box != None:
					self.boxes[self.spots_list[i].box].remove(self.spots_list[i])
				else:
					self.out_of_boxes.remove(self.spots_list[i])
				# Ajoute au groupe
				self.boxes[box].append(self.spots_list[i])
				self.spots_list[i].set_box(box)
	
	def remove_spots_from_box(self, spots, box):
		""" Retire les spots à un groupe

		"""
		if box not in range(0, len(self.boxes)):
			verbose("Undefine box '"+str(box)+"'", 8)
			return
		for i in spots:
			if i not in range(0, len(self.spots_list)):
				verbose("Undefine spot '"+str(i)+"'", 8)
				continue
			if self.spots_list[i] in self.boxes[box]:
				# Supprime de l'ancien groupe
				if self.spots_list[i].box != None:
					self.boxes[self.spots_list[i].box].remove(self.spots_list[i])
					self.out_of_boxes.append(self.spots_list[i])

	def get_info(self):
		""" Revoi une liste structurée des infos des spots

		"""
		reply = {}
		reply["spots"]={}
		for i in range(0, len(self.spots_list)):
			reply["spots"][i] = self.spots_list[i].get_info()
		reply["available_colors"] = self.available_colors
		reply["available_frequencies"] = self.available_freq
		reply["boxes"] = {}
		for i in range(0, len(self.boxes)):
			reply["boxes"][i] = []
			for j in range(0, len(self.boxes[i])):
				verbose(self.boxes[i][j].address, 8)
				reply["boxes"][i].append(self.boxes[i][j].address)
		return(reply)

	def set_from_file(self, file, spots_add, port, baudrate=115200, timeout=10):
		""" Crèe un nouveau réseau à partir d'un fichier de configuration.

		Contrairement à .new, ne charge pas la configuration depuis la mémoire des spots, mais depuis un fichier.
		Valide la configuration via les numéros de série (driver+pcb)
		"""

		verbose(file, 8)
		self.ser = self.set_serial(port=port, baudrate=baudrate, timeout=timeout)
		if self.ser == None:
			return

		spots_add = list(set(spots_add)) # adresse unique
		self.ser.open()

		# Ajoute les spots
		if self.ser.name == port: # port série correctement configuré
			for i in range(0, len(spots_add)):
				if type(address) is int:
					s = Spot()
					
					s.new(ser = self.ser, address = address, grid_id = grid_id)
					
					self.spots_list.append(s) # ajoute le spot à la liste
					self.available_colors = sorted(list(set(self.available_colors + s.available_colors))) # ajoute les couleurs disponibles, et élimine les doublons
					self.available_freq = sorted(list(set(self.available_freq + s.available_freq))) # ajoute les fréquences disponibles, et élimine les doublons
				else:
					verbose("Invalid spot address '"+str(address)+"'", 8)

	def activate(self):
		for s in self.spots_list:
			s.activate()
			self.available_colors = sorted(list(set(self.available_colors + s.available_colors))) # ajoute les couleurs disponibles, et élimine les doublons
			self.available_freq = sorted(list(set(self.available_freq + s.available_freq))) # ajoute les fréquences disponibles, et élimine les doublons

	def activate2(self, config):
		for s in self.spots_list:
			verbose(s.address, 8)
			for k,v in config["spots"].items():
				if int(v["address"]) == int(s.address):
					cdict = config["spots"][k]["channels"]
					s.activate2(cdict, avcol = config["spots"][k]["available_colors"] )
		self.available_colors = config["spots"]["0"]["available_colors"]


	def new(self, spots_add, port, baudrate=115200, timeout=2):
		""" Crèe un nouveau réseau

		"""
		# Port Série
		self.ser = self.set_serial(port=port, baudrate=baudrate, timeout=timeout)
		if self.ser == None:
			return

		# Ajoute les spots
		spots_add = list(set(spots_add)) # adresse unique
		self.ser.open()
		if self.ser.name == port: # port série correctement configuré
			for i in range(0, len(spots_add)):
				self.add_spot(spots_add[i], grid_id = i)
		else:
			verbose("Serial port '"+port+"' is wrong", 8)
		self.ser.close()
