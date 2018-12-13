#!/usr/local/bin/Rscript

logfile <- "log.txt"
uu      <- file(logfile, open = "at")
sink(uu, type = "message")
message(paste("getConnected.R at", Sys.time()))

#### Librairies ###########################################
library("RSQLite")
source("./bin/config.R")

#### Connexion à la base de données
con <- dbConnect(SQLite(), dbname = db)

luminaires <-  dbGetQuery(con, paste0("SELECT * FROM luminaire"))

s.option <- paste("-s", paste(luminaires$address, collapse = " "))

DMXcommand <- paste(s.option, "--test --json --quiet")

if(development) {
  command <- paste("./bin/getConnected_dev.sh")
} else {
  command <- paste(python.cmd, "-p", port, DMXcommand)
}

message(command)
system(command, ignore.stderr = TRUE)
 
zz<-dbDisconnect(con)
