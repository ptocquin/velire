#!/usr/local/bin/Rscript

logfile <- "log.txt"
uu      <- file(logfile, open = "wt")
sink(uu, type = "message")

#### Args #################################################
args         <- commandArgs(TRUE)

# juste pour tester
if(length(args) == 0) {
  message("Analyse en mode test...")

} else {
  message("Run.R")
  cluster_id <- args[1]
  recipe_id  <- args[2] 
}

#### Librairies ###########################################
library("RSQLite")
# library("data.table")

#### Parameters
source("./bin/config.R")

#### Connexion à la base de données
con <- dbConnect(SQLite(), dbname = db)

cluster   <- dbGetQuery(con, paste0("SELECT * FROM cluster WHERE id='", cluster_id, "'"))
luminaires <-  dbGetQuery(con, paste0("SELECT * FROM luminaire WHERE cluster_id='", cluster_id, "'"))
recipe   <- dbGetQuery(con, paste0("SELECT * FROM recipe WHERE id='", recipe_id, "'"))
ingredients <- dbGetQuery(con, paste0("SELECT * FROM ingredient WHERE recipe_id='", recipe$id, "'"))

s.option <- paste("-s", paste(luminaires$address, collapse = " "))
c.option <- "-c"
i.option <- "-i"
for (id in ingredients$id) {
  ingredient <- ingredients[ingredients$id == id, ]
  led   <- dbGetQuery(con, paste0("SELECT * FROM led WHERE id='", ingredient$led_id, "'"))
  c.option <- paste(c.option, paste(led$type, led$wavelength, sep = "_"))
  i.option <- paste(i.option, ingredient$level)
}

zz<-dbDisconnect(con)

DMXcommand <- paste(s.option, c.option, i.option)

command <- paste("python3 ./bin/veliregui-demo.py -p", port, DMXcommand)
message(command)
system(command, ignore.stderr = TRUE)
 


cat("Recipe successfully started on cluster", cluster$label)
