#Challange 
The objective of this challenge is to implement functionality for image uploading and cropping, enabling users to manage and optimize image content efficiently.

🚀 Quick Start 
Install dependencies via Composer: 

composer install

Create a copy of the .env.example file and rename it to .env: 

cp .env.test .env

Run migrations to create the necessary database tables: 

php bin/console doctrine:database:create 

php bin/console doctrine:migrations:migrate

Compile SCSS: 

php bin/console sass:build

Start the server: 

symfony server:start



🔧 Usage 

Log into the web app, create a category, and upload an image.
