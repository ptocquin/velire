#!/usr/local/bin/Rscript

  logfile <- "log.txt"
  uu      <- file(logfile, open = "wt")
  sink(uu, type = "message")

#### Librairies ###########################################
source("./bin/config.R")


s.option <- paste("-s", paste(1:max.lighting, collapse = " "))

DMXcommand <- paste(s.option, "--info")

if(development) {
  command <- paste("./bin/get_data.sh")
} else {
  command <- paste("python3 ./bin/veliregui-demo.py -p", port, DMXcommand)
}
message(command)
system(command, ignore.stderr = TRUE)