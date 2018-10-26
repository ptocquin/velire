#!/usr/local/bin/Rscript

#### Args #################################################
args         <- commandArgs(TRUE)

# juste pour tester
if(length(args) == 0) {
  message("Analyse en mode test...")

} else {
  message("Info.R")
  # cluster_id <- args[1]
  # recipe_id  <- args[2] 
  logfile <- "log.txt"
  uu      <- file(logfile, open = "wt")
  sink(uu, type = "message")
}

#### Librairies ###########################################
library("RSQLite")
# library("data.table")

#### Parameters
command.file <- "./bin/commands"
db <- "../var/data.db"
port     <- "/dev/ttyUSB1"

#### Connexion à la base de données
con <- dbConnect(SQLite(), dbname = db)

# cat(append = F, file = command.file)

luminaires <-  dbGetQuery(con, paste0("SELECT * FROM luminaire WHERE"))

s.option <- paste("-s", paste(luminaires$address, collapse = " "))

DMXcommand <- paste(s.option, "--info")

command <- paste("python3 ./bin/veliregui-demo.py -p", port, DMXcommand)
system(command)
 
zz<-dbDisconnect(con)
