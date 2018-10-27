#!/usr/local/bin/Rscript

Sys.setenv(TZ="Europe/Paris")
logfile <- "log.txt"
uu      <- file(logfile, open = "wt")
sink(uu, type = "message")

min   <- as.numeric(format(Sys.time(), "%M"))
hour  <- as.numeric(format(Sys.time(), "%H"))
day   <- format(Sys.time(), "%Y-%m-%d")


####
message(paste("CheckRun at", day, hour, min))
commands <- read.delim(command.file, header = FALSE, stringsAsFactors = FALSE)

hour2check <- paste0(formatC(hour, flag=0, width = 2), ":",
                     formatC(min, flag=0, width = 2), ":00")

DMXcommand <- commands[commands$V1 == day & commands$V2 == hour2check,]

if(nrow(DMXcommand) == 1){
  command <- paste(python.cmd, "-p", port, DMXcommand$V3)
  message(command)
  system(command)
}