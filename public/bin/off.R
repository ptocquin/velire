#!/usr/local/bin/Rscript

logfile <- "log.txt"
uu      <- file(logfile, open = "at")
sink(uu, type = "message")

#### Args #################################################
args         <- commandArgs(TRUE)

# juste pour tester
if(length(args) == 0) {

} else {
  message("Off.R")
  cluster_id <- args[1]
}

#### Librairies ###########################################
library("RSQLite")
# library("data.table")

#### Parameters
source("./bin/config.R")

#### Connexion à la base de données
con <- dbConnect(SQLite(), dbname = db)

luminaires <-  dbGetQuery(con, paste0("SELECT * FROM luminaire WHERE cluster_id='", cluster_id, "'"))

s.option <- paste("-s", paste(luminaires$address, collapse = " "))

zz<-dbDisconnect(con)

DMXcommand <- paste(s.option, "--off")
command <- paste(python.cmd,"-p", port, DMXcommand)
message(command)

if(!development) {
	system(command, ignore.stderr = TRUE)
}

