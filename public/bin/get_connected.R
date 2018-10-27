#!/usr/local/bin/Rscript

  logfile <- "log.txt"
  uu      <- file(logfile, open = "wt")
  sink(uu, type = "message")

#### Librairies ###########################################
source("./bin/config.R")


s.option <- paste("-s", paste(1:max.lighting, collapse = " "))

DMXcommand <- paste(s.option, "--info")

command <- paste("python3 ./bin/veliregui-demo.py -p", port, DMXcommand)
message(command)
system(command, ignore.stderr = TRUE)
 
zz<-dbDisconnect(con)
