#!/usr/local/bin/Rscript

Sys.setenv(TZ="Europe/Paris")
logfile <- "log.txt"
uu      <- file(logfile, open = "at")
sink(uu, type = "message")

min   <- as.numeric(format(Sys.time(), "%M"))
hour  <- as.numeric(format(Sys.time(), "%H"))
day   <- format(Sys.time(), "%Y-%m-%d")
time  <- format(Sys.time(), "%Y-%m-%d %H:%M:00")

source("./bin/config.R")

####
library("RSQLite")
message(paste("CheckRun at", Sys.time()))

#### On flag les anciens Runs
#### Connexion à la base de données
con <- dbConnect(SQLite(), dbname = db)
runs <-  dbGetQuery(con, paste0("SELECT id FROM run WHERE date_end < '", time,"' OR date_end is NULL")) # AND status != 'past'"))
i <- 0
for (run.id in runs$id) {
	zz <- dbSendQuery(con, paste0("UPDATE run SET status='past' WHERE id='", run.id, "'"))
	if(!file.remove(paste0("./bin/commands@", run.id))) message("Probleme avec la suppresion du fichier", paste0("./bin/commands@", run.id))
	i <- i + 1
}

if(i > 0) message (i, "runs set to 'past' status !")


if(length(cmd.files <- list.files("commands@", path = "./bin", full.names = TRUE)) == 0) quit(save = FALSE, status = 0)

for (cmd.file in cmd.files) {
  commands <- read.delim(cmd.file, header = FALSE, stringsAsFactors = FALSE)
  hour2check <- paste0(formatC(hour, flag=0, width = 2), ":",
                       formatC(min, flag=0, width = 2), ":00")
  
  DMXcommand <- commands[commands$V1 == day & commands$V2 == hour2check,]
  command <- paste(python.cmd, "-p", port, DMXcommand$V3)
  message(command)
  
  if(nrow(DMXcommand) == 1){
    system(command)
  }
}
