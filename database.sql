CREATE DATABASE IF NOT EXISTS movie_scheduler;
USE movie_scheduler;

-- 4. Create the 'Users' table
CREATE TABLE users (
    user_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    pass CHAR(128) NOT NULL,
    birthdate DATE NOT NULL DEFAULT '2000-01-01',
    
    -- New Microservice Preference Columns
    date_region ENUM('US', 'International') NOT NULL DEFAULT 'International',
    date_length ENUM('short', 'long') NOT NULL DEFAULT 'short',
    
    PRIMARY KEY(user_id),
    UNIQUE (email) -- Added to prevent duplicate accounts with the same email
);

-- 5. Create the 'Movies' table
CREATE TABLE movies (
    movie_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    duration_minutes INT NOT NULL,
    rating ENUM('G', 'PG', 'PG-13', 'R', 'NC-17', 'NR') NOT NULL,
    release_year INT
);

-- 6. Create the 'Movie Schedule' table
CREATE TABLE movie_schedule (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    movie_id INT NOT NULL,
    show_date DATE NOT NULL,
    show_time TIME NOT NULL,
    preshow_length ENUM('0', '10', '15', '20') NOT NULL DEFAULT '15',
    total_duration INT NOT NULL,
    cost DECIMAL(10, 2),
    location VARCHAR(255),
    FOREIGN KEY (movie_id) REFERENCES movies(movie_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);