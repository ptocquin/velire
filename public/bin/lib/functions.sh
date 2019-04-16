function check {
	TIME=$(date +"%Y-%m-%d %H:%M:00")
	T=$(date +"%Y-%m-%d\t%H:%M:00")
	# T="2018-12-17\t16:03:30"
	# T="2018-12-17\t16:03:00"
	COUNTER=0

	SQL="SELECT id FROM run WHERE date_end < '$TIME' OR date_end is NULL"
	RUN_IDS=$(sqlite3 "$DB" "$SQL")
	for RUN_ID in $RUN_IDS
	do
		SQL="UPDATE run SET status='past' WHERE id='$RUN_ID'"
		sqlite3 "$DB" "$SQL"
		#SQL="DELETE FROM run_step WHERE start < '$TIME'"
		#sqlite3 "$DB" "$SQL"
		let COUNTER++
	done
	echo "$COUNTER runs set to 'past' status !" >&3

	# CMD_FILES=$(ls ${CMD_FILE}*)
	# if [ ${#CMD_FILES[*]} -eq 0 ]
	# then
	# 	exit 0
	# fi

	# On vérifie si on doit lancer une commande !NOW
	SQL="SELECT count(id) FROM run_step WHERE start = '$TIME'"
	echo "sql: $SQL" >&3
	COUNT=$(sqlite3 "$DB" "$SQL")
	echo "count: $COUNT" >&3

	if [ $COUNT -gt 0 ]
	then
		SQL="SELECT id FROM run_step WHERE start = '$TIME'"
		IDS=$(sqlite3 -newline ' '  "$DB" "$SQL")

		for ID in $IDS
		do
			SQL="SELECT status,command FROM run_step WHERE id = '$ID'"
			STATUS=$(sqlite3 "$DB" "$SQL" | cut -d'|' -f1)
			COMMAND=$(sqlite3 "$DB" "$SQL" | cut -d'|' -f2)

			if [ x$STATUS = "x0" ]
			then
				echo "Commande à executer: $PYTHON_CMD $COMMAND" >&3
				$PYTHON_CMD $COMMAND && {
					echo "Succès" >&3
					SQL="UPDATE run_step SET status=1 WHERE id=$ID"
					sqlite3 "$DB" "$SQL"
				} || echo "Echec" >&3
			fi
		done
	else
		SQL="SELECT id FROM run_step WHERE start < '$TIME' AND status=0"

		IDS=$(sqlite3 -newline ' ' "$DB" "$SQL")
		echo "ids à relancer: $IDS" >&3

		for ID in $IDS
		do
			SQL="SELECT status,command, run_id FROM run_step WHERE id = '$ID'"
			RESULT=$(sqlite3 "$DB" "$SQL")
			STATUS=$(echo $RESULT | cut -d'|' -f1)
			COMMAND=$(echo $RESULT | cut -d'|' -f2)
			RUN_ID=$(echo $RESULT | cut -d'|' -f3)

			if [ x$STATUS = "x0" ]
			then
				echo "Commande à relancer: $PYTHON_CMD $COMMAND" >&3
				$PYTHON_CMD $COMMAND && {
					echo "Succès" >&3
					sqlite3 "$DB" "BEGIN TRANSACTION;UPDATE run_step SET status=2 WHERE run_id=${RUN_ID} AND status=1;UPDATE run_step SET status=1 WHERE id=$ID;COMMIT;"
				} || echo "Echec" >&3
			fi
		done	
	fi

	# for file in $CMD_FILES
	# do
	# 	TMPFILE=$(mktemp)
	# 	echo "Traitement de $file" >&3
	# 	sort $file | cat -n > $TMPFILE

	# 	if grep -qP "$T" "$TMPFILE"
	# 	then
	# 		IDX=$(cat $TMPFILE | grep -P "$T" | cut -f1 | tr -d "[:space:]")
	# 		if grep -qP "$T.*0$" "$TMPFILE"
	# 		then
	# 			CMD=$(cat $TMPFILE | grep -P "$T" | cut -f4)
	# 			echo "Commande à executer: [$IDX] $CMD" >&3
	# 			$CMD &&
	# 			cat $TMPFILE | cut -f2- | rev | sed "${IDX}s/^0/1/" | rev > $file
	# 		else
	# 			cat $TMPFILE | cut -f2- > $file
	# 			echo "La commande $IDX a déjà été executée..." >&3
	# 		fi
	# 	else
	# 		IDX=$(cat $TMPFILE | grep -P -B1 "$T" | head -n1 | cut -f1 | tr -d "[:space:]")
	# 		CMD=$(cat $TMPFILE | grep -P -B1 "$T" | head -n1 | cut -f4)
	# 		echo "On vérifie la commande précédente: $IDX" >&3
	# 		if [ $IDX -eq 0 ]
	# 		then
	# 			echo "On relance la commande $IDX" >&3
	# 			$CMD &&
	# 			cat $TMPFILE | cut -f2- | rev | sed '${IDX}s/^0/1/' | rev > $file
	# 		fi
	# 	fi
	# 	rm $TMPFILE
	# done
}

function init {
	if [ $DEVELOPMENT = "TRUE" ]
	then
		echo "init_dev" >&3
		cat "./bin/lib/connected_dev.json"
	else
		echo "init_prod" >&3
		LIGHTINGS=$(sqlite3 $DB "select address from luminaire")
		$PYTHON_CMD -p $PORT -s $LIGHTINGS --test --json --init --quiet
	fi
}

function save {
	# Sélection des luminaires connectés: status < 99
	SQL="SELECT l0_.address AS address FROM luminaire l0_ \
		LEFT JOIN luminaire_luminaire_status l2_ ON l0_.id = l2_.luminaire_id \
		LEFT JOIN luminaire_status l1_ ON l1_.id = l2_.luminaire_status_id \
		WHERE l1_.code < 99;"
	if [ $DEVELOPMENT = "TRUE" ]
	then
		LIGHTINGS=$(sqlite3 $DB "$SQL")
		echo "$PYTHON_CMD -p $PORT -s $LIGHTINGS --info all --quiet --json --output $CONFIG"
	else
		LIGHTINGS=$(sqlite3 $DB "$SQL")
		$PYTHON_CMD -p $PORT -s $LIGHTINGS --info all --quiet --json --output $CONFIG
	fi	
}

function off {
	CLUSTER=$1
	SQL="SELECT address FROM luminaire \
		WHERE cluster_id='$CLUSTER'"
	if [ $DEVELOPMENT = "TRUE" ]
	then
		LIGHTINGS=$(sqlite3 $DB "$SQL")
		echo "$PYTHON_CMD -p $PORT -s $LIGHTINGS --input $CONFIG --off"
	else
		LIGHTINGS=$(sqlite3 $DB "$SQL")
		$PYTHON_CMD -p $PORT -s $LIGHTINGS --input $CONFIG --off 
	fi
}

function log {
	# Sélection des luminaires connectés: status < 99
	SQL="SELECT l0_.address AS address FROM luminaire l0_ \
		LEFT JOIN luminaire_luminaire_status l2_ ON l0_.id = l2_.luminaire_id \
		LEFT JOIN luminaire_status l1_ ON l1_.id = l2_.luminaire_status_id \
		WHERE l1_.code < 99;"
	if [ $DEVELOPMENT = "TRUE" ]
	then
		cat ./bin/lib/info_dev.json | ./bin/log.R
	else
		LIGHTINGS=$(sqlite3 $DB "$SQL")
		$PYTHON_CMD -p $PORT -s $LIGHTINGS --info all --quiet --json | ./bin/log.R
	fi	
}

function play {
	CLUSTER=$1
	RECIPE=$2
	DAYSTART=$(date "+%Y-%m-%d")
	HOURSTART=$(date "+%H:%M:%S")

	SQL1="SELECT address FROM luminaire \
		WHERE cluster_id='$CLUSTER'"
	SQL2="SELECT l.type, l.wavelength FROM ingredient i \
		LEFT JOIN led l ON l.id=i.led_id \
		WHERE recipe_id=16 \
		ORDER BY i.id"
	SQL3="SELECT i.level FROM ingredient i \
		LEFT JOIN led l ON l.id=i.led_id \
		WHERE recipe_id=16 \
		ORDER BY i.id"

	LIGHTINGS=$(sqlite3 -newline " " $DB "$SQL1")
	COLORS=$(sqlite3 -newline " " -separator "_" $DB "$SQL2")
	LEVELS=$(sqlite3 -newline " " $DB "$SQL3")

	CMD="$PYTHON_CMD -p $PORT -s $LIGHTINGS -c $COLORS -i $LEVELS --input $CONFIG"

	if [ $DEVELOPMENT = "TRUE" ]
	then
		echo "$CMD" >&3
		echo "Recipe successfully started on cluster $CLUSTER" >&3
		printf "$DAYSTART\t$HOURSTART\t$CMD\t0" >> ${CMD_FILE}$CLUSTER
	else
		printf "$DAYSTART\t$HOURSTART\t$CMD\t0" >> ${CMD_FILE}$CLUSTER
		$CMD &&
		echo "Recipe successfully started on cluster $CLUSTER" >&3 ||
		echo "Problem for starting recipe $RECIPE on cluster $CLUSTER" >&3
	fi
}

function run {
	# Read first argument as Run.id
	RUN_ID=$1

	# Fetch Run and deduce Cluster
	SQL="select * from run where id='$RUN_ID'"
	RUN=$(sqlite3 -csv $DB "$SQL")
	CLUSTER=$(echo "$RUN" | cut -d',' -f2)

	#  Fichier des commandes
	TMPFILE=$(mktemp)
	OUTPUT="${CMD_FILE}${CLUSTER}"

	# Fetch lightings addresses
	SQL="select l.address from run r \
		left join luminaire l on r.cluster_id = l.cluster_id \
		where r.cluster_id = '$CLUSTER'"
	LIGHTINGS=$(sqlite3 -newline " " $DB "$SQL")
	S_OPT="-s $LIGHTINGS"

	# Fetch Steps
	SQL="select s.type, s.value, s.recipe_id from run r \
		left join step s on r.program_id = s.program_id \
		where r.id = '$RUN_ID'"
	STEPS=($(sqlite3 -csv "$DB" "$SQL"))

	# Parse steps
	STEP_START=$(echo "$RUN" | cut -d',' -f8 | tr -d '"')
	GOTO=0
	STEP_INDEX=0
	echo "Total steps: ${#STEPS[*]}"
	while [ $STEP_INDEX -lt ${#STEPS[*]} ]
	do
		echo "Index1: ${STEP_INDEX}"
		STEP=${STEPS[$STEP_INDEX]}
		STEP_TYPE=$(echo "$STEP" | cut -d',' -f1)
		echo "Type: $STEP_TYPE"
		# Si le step = TIME
		if [ $STEP_TYPE = "time" ]
		then
			echo "Time !"
			STEP_VALUE=$(time2seconds "$STEP" 2)
			DAY_START=$(echo "$STEP_START" | cut -d' ' -f1)
			HOUR_START=$(echo "$STEP_START" | cut -d' ' -f2)
			RECIPE_ID=$(echo "$STEP" | cut -d',' -f3)
			echo $RECIPE_ID

			SQL="select l.type, l.wavelength from recipe r \
				left join ingredient i on r.id = i.recipe_id \
				left join led l on i.led_id = l.id \
				where r.id='$RECIPE_ID'"
			C_OPT="-c $(sqlite3 -newline " " -separator "_" "$DB" "$SQL")"
			echo $C_OPT

			SQL="select i.level from recipe r \
				left join ingredient i on r.id = i.recipe_id \
				left join led l on i.led_id = l.id \
				where r.id='$RECIPE_ID'"
			I_OPT="-i $(sqlite3 -newline " " "$DB" "$SQL")"
			echo $I_OPT

			CMD="$PYTHON_CMD -p $PORT $S_OPT $C_OPT $I_OPT --input $CONFIG"
			printf "$DAY_START\t$HOUR_START\t$CMD\t0\n" >> ${TMPFILE}
			echo "$STEP_START + $STEP_VALUE"
			STEP_START=$(add_duration "${STEP_START}" "${STEP_VALUE}")
			let STEP_INDEX++
			echo "Index2: ${STEP_INDEX}"
		fi

		# Si le step = GOTO
		if [ $STEP_TYPE = "goto" ]
		then
			echo "Goto: $GOTO"
			if [ $GOTO -eq 0 ]
			then
				GOTO=$(time2goto "$STEP" 2 2)
				STEP_INDEX=$(time2goto "$STEP" 2 1)
				echo "to step: ${STEP_INDEX}"
				continue
			fi
			if [ $GOTO -eq 1 ]
			then
				let STEP_INDEX++
				let GOTO--
				echo "to step: ${STEP_INDEX}"
				continue
			fi
			if [ $GOTO -gt 1 ]
			then
				STEP_INDEX=$(time2goto "$STEP" 2 1)
				let GOTO--
				echo "to step: ${STEP_INDEX}"
				continue
			fi
		fi

		# Si le step = OFF
		if [ $STEP_TYPE = "off" ]
		then
			STEP_VALUE=$(time2seconds "$STEP" 2)
			DAY_START=$(echo "$STEP_START" | cut -d' ' -f1)
			HOUR_START=$(echo "$STEP_START" | cut -d' ' -f2)
			CMD="$PYTHON_CMD -p $PORT $S_OPT --off"
			printf "$DAY_START\t$HOUR_START\t$CMD\t0\n" >> ${TMPFILE}
			STEP_START=$(add_duration "${STEP_START}" "${STEP_VALUE}")
			let STEP_INDEX++
		fi
	done

	sort $TMPFILE >> $OUTPUT
	cat $OUTPUT | sort | uniq > $TMPFILE && cp $TMPFILE $OUTPUT
	rm $TMPFILE

	DATE_END="${STEP_START}"
	SQL="update run set date_end='$DATE_END' where id=$RUN_ID"
	echo $SQL
	sqlite3 "$DB" "$SQL"
}

function delete {
	FILE="${CMD_FILE}$1"
	if [ -e $FILE ]
	then
		rm -f $FILE
	fi
}

function parse_yaml {
	cat $1 | sed -e 's/:[^:\/\/]/="/g;s/$/"/g;s/ *=/=/g'
}

function time2seconds {
	S1=$(($(echo "$1" | cut -d',' -f$2 | cut -d':' -f1)*3600))
	S2=$(($(echo "$1" | cut -d',' -f$2 | cut -d':' -f2)*60))
	echo $(($S1+$S2))
}

function time2goto {
	echo $(($(echo "$1" | cut -d',' -f$2 | cut -d':' -f$3)))
}

function add_duration {
	U=$(uname -s)
	if [ $U = "Darwin" ]
	then
		date -v+${2}S -jf "%Y-%m-%d %H:%M:%S" "${1}" +"%Y-%m-%d %H:%M:%S"
	else
		H=$(echo "$1" | cut -d' ' -f2)
		D=$(echo "$1" | cut -d' ' -f1)
		date -d "$H $D + $2 seconds" +"%Y-%m-%d %H:%M:%S"
	fi
}