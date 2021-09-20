if(!require(tidyverse)) install.packages("tidyverse", repos = "http://cran.us.r-project.org")
library(stringr)

test_split = 0.2
test_latest=TRUE
source = "bidvest.csv"

  ds <- read.csv(source)
  ds <- ds%>%select(ac, comm, dt)
  #ds$ac <- as.factor(as.character(ds$ac))
  ds$ac <- as.character(ds$ac)
  ds$comm <- sapply(ds$comm,function(c) {
    c <- gsub(',','',c,fixed=TRUE)
    c <- gsub('[0-9]{2}[A-Z]{3}[0-9]{2} ','',c)
    c <- gsub('\\s{2,}',' ',c)
    trimws(toupper(gsub('^C ','',c)))
  })
  
  # Split Data into Training and Testing 
  sample_size <- floor(test_split*nrow(ds))
  
  if (test_latest) {
    # take latest data to use as test
    train <- ds%>%arrange(dt)
    test <- tail(train,sample_size)
    train <- head(train, -sample_size)

  } else {
    # randomly split data in r
    set.seed(777)
    holdout <- sample(seq_len(nrow(ds)),size = sample_size)
    test <- ds[holdout,]
    train <- ds[-holdout,]
  }
  
  #Tokenize
  tokens <- tibble(token=character(),ac=integer(), remove=integer())
  i <- 1
  while (i <= length(train$comm)){
    #monograms
    mlist <- unlist(strsplit(train$comm[i]," "))
    ### Add in bigrams
    #j <- 1
    #blist = c()
    #while (j < length(mlist)) {
    #  blist <- append(blist,paste(mlist[j],mlist[j+1]))
     # j <- j + 1
    #}
    ### Add in trigrams
    #j <- 1
    #tlist = c()
    #while (j < (length(mlist) - 1)) {
      #tlist <- append(tlist,paste(mlist[j],mlist[j+1],mlist[j+2]))
      #j <- j + 1
    #}
    #mlist <- c(mlist, blist, tlist)
    tokens <- rbind(tokens, tibble(token = mlist, ac= rep(train$ac[i],length(mlist))))
    i <- i + 1
    }
  tokens <- tokens%>%group_by(token,ac)%>%summarize(count=n())
  tokens <- tokens%>%group_by(token)%>%summarize(countac=n(), first_ac = first(ac), freq=sum(count))
  tokens <- tokens%>%filter(countac == 1)%>%arrange(desc(freq))
  
  #eliminate unnecessary tokens
  
  i <- 1
  removals <- c()
  while (i < nrow(tokens)) {
    if (grepl(tokens[i,'token'], tokens[i+1,'token'], fixed=TRUE) && 
        tokens[i,'first_ac'] == tokens[i+1,'first_ac'] &&
        tokens[i,'freq'] >= tokens[i+1,'freq']) {
      removals <- c(removals,i+1)
    }
    i <- i+1
  }
  tokens <- tokens[-removals,]
  tokens <- tokens%>%mutate(token = paste(" ",token," ",sep=''))
  
  tok_list <- c()
  y_hat <- c()
  j <- 1
  while (j <= length(test$comm)){
    i <- 1
    guess <- "0"
    tok <- "NONE"
    while (i <= nrow(tokens)) {
      if (grepl(tokens[i,'token'],paste(" ", test$comm[j], " ", sep=''), fixed=TRUE)) {
        guess<-tokens[i,'first_ac']
        tok <- tokens[i,'token']
        break
      }
      i = i + 1
    }
    tok_list <- c(tok_list,tok)
    y_hat <- c(y_hat, guess)
    j <- j+1
  }
y_hat <- unlist(y_hat)
tok_list <- unlist(tok_list)

results <- tibble(com=test$comm,tok=tok_list, actual=test$ac, guess=y_hat)
results <- results %>% mutate(acc= case_when(actual==guess ~ "GOOD", guess=='0' ~ "?", TRUE ~ "BAD"))
view(results)
results%>%group_by(acc)%>%summarise(n=n())




