#!/usr/local/bin/Rscript

#### Args #################################################

  logfile <- "log.txt"
  uu      <- file(logfile, open = "at")
  sink(uu, type = "message")
  message(paste("Log at", Sys.time()))

#### Librairies ###########################################
library("RSQLite")
library("jsonlite")

source("./bin/config.R")

#### Connexion à la base de données
con <- dbConnect(SQLite(), dbname = db)

now  <- format(Sys.time(), "%Y-%m-%d %H:%M:%S")
luminaires <- dbGetQuery(con, "SELECT * FROM luminaire")

if(nrow(luminaires) == 0) quit(save = "no", status = 0)

s.option <- paste("-s", paste(luminaires$address, collapse = " "))
DMXcommand <- paste(s.option, "--info")

if(development) {
  command <- paste("./bin/get_data.sh")
} else {
  command <- paste(python.cmd, "-p", port, DMXcommand)
}

message(command)

json <- system(command, intern = TRUE)
data <- fromJSON(json)
spots <- data$spots
cluster_array <- list()
clusters <- c()
for (spot in spots) {
  luminaire <- dbGetQuery(con, sprintf("SELECT * FROM luminaire WHERE address = '%s'", spot$address))

  # Création de la liste des clusters
  if(! luminaire$cluster_id %in% clusters) clusters <- c(clusters, luminaire$cluster_id)
  # run <- dbGetQuery(con, sprintf("SELECT * FROM run WHERE cluster_id = %s and start <= '%s' AND date_end >= '%s'", luminaire$cluster_id, now, now))

  # Temp data du luminaire en version JSON
  luminaire_info <- toJSON(list(address = spot$address, serial = spot$serial, led_pcb_0 = spot$temperature$led_pcb_0, led_pcb_1 = spot$temperature$led_pcb_1), auto_unbox = TRUE)
  # Collecte de l'information température à l'échelle du cluster
  cluster_array[[as.character(luminaire$cluster_id)]]$temp <- c(cluster_array[[as.character(luminaire$cluster_id)]]$temp, spot$temperature$led_pcb_0, spot$temperature$led_pcb_1)

  dbSendQuery(con, sprintf("INSERT INTO Log (time, type, luminaire_id, cluster_id, value, comment) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')", now, "luminaire_info", luminaire$id, luminaire$cluster_id, luminaire_info, ""))

  channels <- spot$channels

  for (channel in channels){
    cluster_array[[as.character(luminaire$cluster_id)]]$channels$colors <- c(cluster_array[[as.character(luminaire$cluster_id)]]$channels$colors, channel$color)
    cluster_array[[as.character(luminaire$cluster_id)]]$channels$intensity <- c(cluster_array[[as.character(luminaire$cluster_id)]]$channels$intensity, channel$intensity)
  }

}

for (cluster in clusters){
  cluster_temp <- cluster_array[[as.character(cluster)]]$temp
  channels_on  <- length(which(cluster_array[[as.character(cluster)]]$channels$intensity > 0))
  data         <- toJSON(list(min_temp = min(cluster_temp), max_temp = max(cluster_temp), mean_temp = mean(cluster_temp), 
    channels_on = channels_on, colors = cluster_array[[as.character(cluster)]]$channels$colors, intensity = cluster_array[[as.character(cluster)]]$channels$intensity), auto_unbox = TRUE)
  dbSendQuery(con, sprintf("INSERT INTO Log (time, type, cluster_id, value, comment) VALUES ('%s', '%s', '%s', '%s', '%s')", 
                           now, "cluster_info", cluster, data, ""))
}


zz<-dbDisconnect(con)
