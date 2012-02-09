drop table if exists user;
create table user (
	id int not null auto_increment,
	email varchar(255) not null,
	password varchar(255) not null,
	firstname varchar(255) not null,
	lastname varchar(255) not null,
	primary key (id),
	index(email)
);
drop table if exists user_file;
create table user_file (
	id int not null auto_increment,
	user_id int not null,
	filename varchar(255) not null,
	filetype varchar(255) not null,
	filedata blob,
	primary key (id),
	index (user_id)
);
drop table if exists category;
create table category (
	id int not null auto_increment,
	parent_category_id int default null,
	name varchar(255) not null,
	description text not null,
	primary key (id),
	index(parent_category_id)
);
drop table if exists user_category;
create table user_category (
	id int not null auto_increment,
	category_id int not null,
	user_id int not null,
	primary key (id),
	index (category_id),
	index (user_id)
);
insert into user set email='test@email.local', password='testpassword',firstname='Bob',lastname='Smith';
insert into user set email='test2@email.local', password='testpassword',firstname='Sue',lastname='Saunders';
insert into user set email='test3@email.local', password='testpassword',firstname='Dave',lastname='Scott';
insert into user set email='test4@email.local', password='testpassword',firstname='Sandra',lastname='Seabrook';
insert into user set email='test5@email.local', password='testpassword',firstname='Ellie',lastname='Schofield';
insert into user set email='test6@email.local', password='testpassword',firstname='Tom',lastname='Sears';
