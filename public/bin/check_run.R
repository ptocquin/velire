#!/usr/local/bin/Rscript

#### Args #################################################
# args         <- commandArgs(TRUE)
# 
# # juste pour tester
# if(length(args) == 0) {
#   message("Analyse en mode test...")
#   min   <- 30
#   hour  <- 11
#   day   <- "2016-10-01"
#   
# } 

message("CheckRun")
Sys.setenv(TZ="Europe/Paris")
logfile <- "log.txt"
uu      <- file(logfile, open = "wt")
sink(uu, type = "message")

min   <- as.numeric(format(Sys.time(), "%M"))
hour  <- as.numeric(format(Sys.time(), "%H"))
day   <- format(Sys.time(), "%Y-%m-%d")

#### Librairies ###########################################
# message(installed.packages())
# library("RSQLite", quietly = TRUE)
# library("data.table", quietly = TRUE)
# library("rjson", quietly = TRUE)

####
message(paste("CheckRun at", day, hour, min))
commands <- read.delim("./bin/commands", header = FALSE, stringsAsFactors = FALSE)
port     <- "/dev/ttyUSB0"
con <- dbConnect(SQLite(), dbname="../../var/data.db")

hour2check <- paste0(formatC(hour, flag=0, width = 2), ":",
                     formatC(min, flag=0, width = 2), ":00")

DMXcommand <- commands[commands$V1 == day & commands$V2 == hour2check,]

if(nrow(DMXcommand) == 1){
  command <- paste("python3 velire -p", port, DMXcommand$V3)
  system(command)
}


dbDisconnect(con)
