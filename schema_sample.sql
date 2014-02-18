/*
Version: 0.7
Date: 15/01/2014
Author: Rui Oliveira
*/



START TRANSACTION;

drop database if exists warehouse;

CREATE DATABASE warehouse;


USE warehouse;


/*CREATE USER 'server'@'localhost' IDENTIFIED BY 'myWardrobeIsbetter';
GRANT ALL PRIVILEGES ON wardrobe.* TO 'server'@'localhost';*/
grant all on `warehouse`.* to 'server'@'localhost' identified by 'password';


/*CREATE TABLES COMMANDS*/
CREATE TABLE entity(
	id INT NOT NULL AUTO_INCREMENT,
	email VARCHAR(50) NOT NULL,
	name VARCHAR(20) NOT NULL,
	register_date datetime NOT NULL,
	PRIMARY KEY(id)
)ENGINE=InnoDB, CHARSET = utf8;


CREATE TABLE users(
	id INT NOT NULL AUTO_INCREMENT, 
    password BINARY(40) NOT NULL, /*UNHEX(SHA1("mypassword"))*/
	salt BINARY(16) NOT NULL,
	entity_id INT NOT NULL,		
	isMale TINYINT(1) DEFAULT 1, /*1 - male | 0 - famale*/ 	
	birth date,	
	phone VARCHAR(15),
	nacionality VARCHAR(20),
	email_confirmed TINYINT(1) DEFAULT 0, 
	FOREIGN KEY(entity_id) REFERENCES entity(id) ON DELETE CASCADE,
	PRIMARY KEY(id)
)ENGINE=InnoDB, CHARSET = utf8;

CREATE TABLE shop(
	id INT NOT NULL AUTO_INCREMENT, 
	entity_id INT NOT NULL,
	FOREIGN KEY(entity_id) REFERENCES entity(id) ON DELETE CASCADE,
	PRIMARY KEY(id)
)ENGINE=InnoDB, CHARSET = utf8;

CREATE TABLE session_on(
	session_token BINARY(32) NOT NULL,
	logout_time datetime NOT NULL,
	entity_id INT NOT NULL,
	FOREIGN KEY(entity_id) REFERENCES entity(id) ON DELETE CASCADE,
	PRIMARY KEY (entity_id)
)ENGINE=InnoDB, CHARSET = utf8;


CREATE TABLE brand(
	id INT NOT NULL AUTO_INCREMENT, 
	name VARCHAR(20) NOT NULL,
	official TINYINT(1) DEFAULT 0, 
	PRIMARY KEY(id)
)ENGINE=InnoDB, CHARSET = utf8;

CREATE TABLE type(
	id INT NOT NULL AUTO_INCREMENT, 
	name VARCHAR(20) NOT NULL,
	description VARCHAR(40),
	PRIMARY KEY(id)
)ENGINE=InnoDB, CHARSET = utf8;


CREATE TABLE item(
	id INT NOT NULL AUTO_INCREMENT,
	description VARCHAR(40),
	rating TINYINT(10), /*rating 1 to 10, 0 is: not assigned*/
	brand_id INT,			
	type_id INT, 
	price FLOAT(6,2),

	FOREIGN KEY(brand_id) REFERENCES brand(id) on DELETE CASCADE,
	FOREIGN KEY(type_id) REFERENCES entity(id) ON DELETE CASCADE,
	PRIMARY KEY(id)
)ENGINE=InnoDB, CHARSET = utf8;

CREATE TABLE shopping(
	id INT NOT NULL AUTO_INCREMENT,
	entity_id INT NOT NULL,
	item_id INT NOT NULL,
	shopping_date date,
	quantity INT,
	price FLOAT(6,2),

	FOREIGN KEY(entity_id) REFERENCES item(id) on DELETE CASCADE,
	FOREIGN KEY(item_id) REFERENCES type(id) ON DELETE CASCADE,
	PRIMARY KEY(id)
)ENGINE=InnoDB, CHARSET = utf8;



COMMIT;
