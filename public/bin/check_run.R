#!/usr/local/bin/Rscript

Sys.setenv(TZ="Europe/Paris")
logfile <- "log.txt"
uu      <- file(logfile, open = "at")
sink(uu, type = "message")

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


if(length(cmd.files <- list.files("commands@", path = "./bin", full.names = TRUE)) == 0) quit(save = "no", status = 0)

for (cmd.file in cmd.files) {
  commands <- read.delim(cmd.file, header = FALSE, stringsAsFactors = FALSE)
  commands$datetime <- paste(commands$V1, commands$V2)
  commands <- commands[order(commands$datetime),]
  
  DMXcommand <- commands[commands$datetime == time,]
  command <- paste(python.cmd, "-p", port, DMXcommand$V3)
  
  if(nrow(DMXcommand) == 1){
    message("On lance la commande ", command)
    system(command)
    commands[commands$datetime == time,]$V4 <- 1
    write.table(commands, file = cmd.file, sep = "\t", row.names = FALSE, col.names = FALSE)
  } else {
    # On vérifie si la dernière commande a bien été exécutée, sinon
    # on la relance
    
    last.cmd <- tail(commands[commands$datetime < time,], 1)
    if(last.cmd$V4 == 0){
      # Commande non executée
      command <- paste(python.cmd, "-p", port, last.cmd$V3)
      message("On relance la commande non executée: ", command)
      system(command)
      commands[last.cmd$datetime,]$V4 <- 1
      write.table(commands, file = cmd.file, sep = "\t", row.names = FALSE, col.names = FALSE)
    } else {
      
    }
  }
}
