#!/usr/local/bin/Rscript

#### Args #################################################
args         <- commandArgs(TRUE)

run.id <- args[1]

message("Run.R")
logfile <- "log.txt"
uu      <- file(logfile, open = "wt")
sink(uu, type = "message")

#### Librairies ###########################################
library("RSQLite")
library("jsonlite")

source("./bin/config.R")

#### Connexion à la base de données
con <- dbConnect(SQLite(), dbname = db)

runs <-  dbGetQuery(con, paste0("SELECT id FROM run"))

# clusters <- list()
# for (run.id in runs$id) {
  output <- paste0(command.file, run.id)
  # if(!file.create(output)) quit()
  cat(append = TRUE, file = output)
  run     <-  dbGetQuery(con, paste0("SELECT * FROM run WHERE id='", run.id, "'"))
  steps   <-  dbGetQuery(con, paste0("SELECT * FROM step WHERE program_id='", run$program_id, "'"))
  luminaires <-  dbGetQuery(con, paste0("SELECT * FROM luminaire WHERE cluster_id='", run$cluster_id, "'"))
  
  s.option <- paste("-s", paste(luminaires$address, collapse = " "))
  
  date.start <- strptime(run$start, "%Y-%m-%d %H:%M:%S")
  step.start <- date.start
  step <- 0
  read <- 1
  goto <- 0
  while (step <= max(steps$rank)) {
    current.step <- steps[steps$rank == step,]
    type <- current.step$type
    day.start <- unlist(strsplit(as.character(step.start), " "))[1]
    hour.start <- unlist(strsplit(as.character(step.start), " "))[2]
    if (type == "time") {
      duration <- as.numeric(unlist(strsplit(current.step$value, ":"))[1])*3600 + as.numeric(unlist(strsplit(current.step$value, ":"))[2])*60
      recipe   <- dbGetQuery(con, paste0("SELECT * FROM recipe WHERE id='", current.step$recipe_id, "'"))
      ingredients <- dbGetQuery(con, paste0("SELECT * FROM ingredient WHERE recipe_id='", recipe$id, "'"))
      
      c.option <- "-c"
      i.option <- "-i"
      for (id in ingredients$id) {
        ingredient <- ingredients[ingredients$id == id, ]
        led   <- dbGetQuery(con, paste0("SELECT * FROM led WHERE id='", ingredient$led_id, "'"))
        c.option <- paste(c.option, paste(led$type, led$wavelength, sep = "_"))
        i.option <- paste(i.option, ingredient$level)
      }
      
      command <- paste(s.option, c.option, i.option)

      # A vérifier si toujours nécessaire
      # if(length(unlist(strsplit(as.character(step.start), " "))) == 1) {
      #   zz <- unlist(strsplit(as.character(step.start), " "))
      #   step.start <- paste(zz, "00:00:00")
      # }
      
      cat(paste(day.start, hour.start, command, sep = "\t"), file = output, append = T, sep = "\n")
      
      message(step.start, duration, command)
      step <- step + 1
      step.start <- step.start+duration
    }
    
    if(type == "off") {
      duration <- as.numeric(unlist(strsplit(current.step$value, ":"))[1])*3600 + as.numeric(unlist(strsplit(current.step$value, ":"))[2])*60
      command <- paste(s.option, "--off")
      cat(paste(day.start, hour.start, command, sep = "\t"), file = output, append = T, sep = "\n")
      message(step.start, duration, command)
      step <- step + 1
      step.start <- step.start+duration
    }
    
    if(type == "goto") {
      if(goto == 0){
        goto <- as.numeric(unlist(strsplit(current.step$value, ":"))[2])
        step <- as.numeric(unlist(strsplit(current.step$value, ":"))[1])
        next
      }
      if(goto == 1){
        step <- step+1
        goto <- goto-1
        next
      }
      if(goto > 1) {
        goto <- goto-1
        step <- as.numeric(unlist(strsplit(current.step$value, ":"))[1])
        next
      }
    }
  }
  # clusters[[as.character(run.id)]]$date_end <- paste(day.start, hour.start)
  # clusters[[as.character(run.id)]]$id <- run.id
# }

zz<-dbSendQuery(con, paste0("UPDATE run SET date_end='", paste(day.start, hour.start),"' WHERE id='", run.id, "'"))
zz<-dbDisconnect(con)

cat(day.start, hour.start)
