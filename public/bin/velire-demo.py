#!/usr/bin/env python3
# -*- coding: utf-8 -*-

'''
------------------------------------------------------------------------------
CONTRÔLEUR DU SPOT VELIRE
------------------------------------------------------------------------------
Anthony Fratamico
Laboratoire de Physiologie Végétale
Institut de Botanique, Université de Liège
18 septembre 2018
------------------------------------------------------------------------------
'''
import sys
import os
import argparse
from velire import velire
from time import sleep
import pprint
import json

# Informations
release = "0.0 (dev, 2018-09-18)"

print("Développement du pilotage du spot VELIRE")
print(release+"\n")

# Réseau et groupe de luminaires
if True == True:
	g = velire.Grid()
	g.new(spots_add=[1], port='/dev/ttyUSB1')
	g.open()
	pprint.pprint(g.get_info())
	g.shutdown()
	g.set_freq(60)
	g.set_bycolor({"colortype": "GR_525", "intensity" : 1, "unit": "%", "start": 0., "stop": 0.25}) # No box arg = conf for all spots in grid
	sleep(2)
	g.shutdown()
	g.new_boxes(n = 1)
	g.add_spots_to_box(spots = [0], box = 0)
	g.set_bycolor(conf = {"colortype": "WH_4000", "intensity" : 1, "unit": "%", "start": 0, "stop": 1}, box = 0) # Only spot in box 0
	sleep(2)
	#g.remove_spots_from_box(spots=[0], box = 0)
	g.shutdown(box = 0)
	sleep(2)
	infos = g.get_info()
	with open("data_grid.json", 'w') as file:
		json.dump(infos, file)
	s = g.get_spot(value = 0, target = "id")
	s = g.get_spot(value = 1, target = "add")
	s.set_bycolor({"colortype": "HR_660", "intensity" : 1, "unit": "%", "start": 0, "stop": 0.5})
	sleep(2)
	s.shutdown()

	g.close()
	exit()

# Défini un spot
s = velire.Spot()
s.new(address=1, port="/dev/ttyUSB1")

# Ouvre le port pour la communication
s.open_serial()
s.shutdown() # éteind

# Imprime les informations sur le spot
if True == False:
	infos = s.get_ledinfo()
	pprint.pprint(infos)
	#print(json.dumps(infos))

# DEMO: allume les canaux un à un puis éteind
if True == False:
	print("DEMO: Allumage de tous les canaux")
	for i in range(0,len(s.channels)):
		print("   "+str(i)+") "+s.channels[i].get_ledinfo("color"))
		param = {"channel": i, "intensity" : 42, "unit": "%", "start": 0, "stop": 1}
		s.set_channel(param)
		#sleep(0.5)
	sleep(10)
	s.shutdown()

# DEMO: allume toutes les led d'une même couleur
if True == False:
	print("DEMO: Allumage des LED de même couleur")
	for led in s.channels_colortype.keys():
		print("   "+led)
		s.set_bycolor({"colortype": led, "intensity" : 5, "unit": "%", "start": 0, "stop": 1})
		sleep(1)
		s.shutdown()

# DEMO: Imprime la température
if True == False:
	print("DEMO: Température:")
	temp = s.get_temp()
	for key, value in temp.items():
		if key != "unit":
			print("   "+key+": "+str(value)+temp["unit"])
	#pprint.pprint(s.get_temp())

# DEMO: Fréquences PWM
if True == False:
	print("DEMO: Test des fréquences du PWM")
	# 4 Canaux déphasés
	s.set_bycolor({"colortype": "HR_660", "intensity" : 1, "unit": "%", "start": 0, "stop": 0.5})
	s.set_bycolor({"colortype": "BL_460", "intensity" : 1, "unit": "%", "start": 0.25, "stop": 0.5})
	s.set_bycolor({"colortype": "GR_525", "intensity" : 2, "unit": "%", "start": 0.5, "stop": 0.75})
	s.set_bycolor({"colortype": "WH_4000", "intensity" : 1, "unit": "%", "start": 0.75, "stop": 1})
	# Test de toutes les fréquences disponibles
	for f in s.available_freq:
		print(str(f)+" Hz")
		r = s.set_freq(f)
		if r != None:
			if 4/f > 2:
				sleep(4/f)
			else:
				sleep(2)
	s.shutdown()

# DEMO: PWM
if True == False:
	print("DEMO: Déphasage PWM")
	freq = 1 # Hz
	s.set_freq(freq)
	dutty_cyle = 1/len(s.channels)
	for i in range(0,len(s.channels)):
		s.set_channel({"channel": i, "intensity" : 1, "unit": "%", "start": i*dutty_cyle, "stop": (i+1)*dutty_cyle})
	sleep(20)
	#s.shutdown()

# DEMO: Statut complet
if True == False:
	print("DEMO: Export des informations détaillées du spot en JSON")
	infos = s.get_info()
	with open("data_spot_"+s.address+".json", 'w') as file:
		json.dump(infos, file)
	pprint.pprint(s.get_info())

# Ferme le port et sort
s.shutdown()
s.close_serial()
exit()