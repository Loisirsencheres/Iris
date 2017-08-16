# Iris marketing campaign attribution

Attribution is essential for e-commerce but it is a complex IT challenge.
Here is php web app built with Silex that collect user touchpoints, process, and provide a fast marketing dashboard.
This is recommanded for small to medium traffic as touchpoints are recorded in Redis and MySQL database.

It has been used in production on a e-commerce website.

## Features

* Collect touchpoints and process them in the background.
* Custom convertion type such as customer registration, add to basket, checkout and payment
* Several attribution model for marketing campaign and sources
    * linear
    * firstclick
    * lastclick (default attribution model in Google Analytics)
    * ascending / descending
    * parabolic

* Dashboarding designed for non technical users to analyse campaigns performances.
* Compare attribution model over diffent timeranges with fast querying



## Requirements

A web serveur, php, Redis, MySQL

## Installation

    # Install dependencies with Composer
    composer install

    # Download Gentelella for UI
    cd web/assets/lib
    wget https://github.com/puikinsh/gentelella/archive/1.3.0.zip
    unzip 1.3.0.zip gentella

    # Make config with Phing
    cd ../../..
    ./vendor/bin/phing web


## Why this project ?
This was built in house by Loisirs Enchères developers for internal needs.
We decided to make it public as marketing attribution is a common problem of e-commerce companies.
This also shows interesting IT topics :
* Structure of a Silex project with configuration management
* Background data processing
* Secure connection to MySQL (aws Aurora) with SSL
* User firendly UI for dashbording

We know this not well documented yet, please feel free to contact us if you want to know more


