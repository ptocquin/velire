#!/usr/local/bin/Rscript

  logfile <- "log.txt"
  uu      <- file(logfile, open = "wt")
  sink(uu, type = "message")

#### Librairies ###########################################
library("RSQLite")
source("./bin/config.R")

#### Connexion à la base de données
con <- dbConnect(SQLite(), dbname = db)

luminaires <-  dbGetQuery(con, paste0("SELECT * FROM luminaire"))

s.option <- paste("-s", paste(luminaires$address, collapse = " "))

DMXcommand <- paste(s.option, "--info")

command <- paste("python3 ./bin/veliregui-demo.py -p", port, DMXcommand)
system(command)
 
zz<-dbDisconnect(con)
