#!/usr/local/bin/Rscript

  logfile <- "log.txt"
  uu      <- file(logfile, open = "at")
  sink(uu, type = "message")
  message(paste("info.R at", Sys.time()))

#### Librairies ###########################################
library("RSQLite")
source("./bin/config.R")

#### Connexion à la base de données
con <- dbConnect(SQLite(), dbname = db)

luminaires <-  dbGetQuery(con, paste0("SELECT * FROM luminaire"))

s.option <- paste("-s", paste(luminaires$address, collapse = " "))

DMXcommand <- paste(s.option, "--info")

if(development) {
  command <- paste("./bin/get_data.sh")
} else {
  command <- paste("python3 ./bin/veliregui-demo.py -p", port, DMXcommand)
}

message(command)
system(command, ignore.stderr = TRUE)
 
zz<-dbDisconnect(con)
