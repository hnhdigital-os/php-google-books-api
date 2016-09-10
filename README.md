Google Books API for PHP
========================
An easy to use php client for using Google Books API.

## Requirements

* [PHP](https://php.net) 5.5+
* [Google API](https://console.developers.google.com) Service account key

## Installation

```bash
$ composer require bluora/php-google-books-api dev-master
```

You can also add this package to your `composer.json` file:

`"bluora/php-google-books-api": "dev-master"`

Then run `composer update` to download the package to your vendor directory.

## Configuration

There are several ways to set the Google API Service account key.

Using your envionrment file, using `putenv` in a config file, or at time of use of the GoogleBookApi class instantiation.

.env file
```
GOOGLE_BOOKS_API_KEY="xxx...xxx"
```

PHP Configuration
```php
putenv('GOOGLE_BOOKS_API_KEY=xxx...xxx'));
```

```php
$lookup = new GoogleBooksApi(['key' => 'xxx...xxx']);
```

## Usage

### Basic

Let's get `The Google story` using it's ISBN.

NOTE: The returned value will be null if the query can't find the record.

```php
$book = (new GoogleBooksApi())
    ->isbn('9780553804577')->first();
```

An associative array of values is returned. The contents of the book data is different based on the geolocation of your the host's IP address.

This package implements Iterator and Countable so it provides both count and the ability to treat it as an array using a foreach.

NOTE: Before returning a result, `count` will load the first `page` of results.


```php

$books = (new GoogleBooksApi())
    ->search('google');

echo 'Total books in result: ' . count($books) . '; Total pages: '.$lookup->totalPages();

```

Would output:
```
Total books in result: 511; Total pages: 52
```

As it implements `Interator`, calling `foreach` will load new books as it reaches the end of the current results.

If you only want to load a certain number of books, then use `limit` to only load more pages of books.

NOTE: Setting the limit will automatically set the number of records per page up to an API maximum of 40. After 40, it will request the remaining values in further API calls.


```php

$books = (new GoogleBooksApi())
    ->search('google')
    ->limit(12);

echo 'Total: '.count($lookup)."; Pages: ".$lookup->totalPages()."\n";

foreach ($lookup as $key => $book) {
    echo $key." - ".$book['title']."\n";
}
```

Would output:
```
Total: 511; Pages: 43
0 - The Google Legacy
1 - Google
2 - The Google Model
3 - Google Chrome
4 - Using Google AdWords and AdSense
5 - Making Google Adsense Work for the 9 to 5 Professional - Tips and Strategies to Earn More from Google Adsense
6 - Google Search & Rescue For Dummies
7 - Using Google and Google Tools in the Classroom, Grades 5 & Up
8 - Programming Google App Engine
9 - Python for Google App Engine
10 - Google and the Law
11 - Planet Google
```

You can call `all` to get the complete array of results.

```php

$books = (new GoogleBooksApi())
    ->search('google')
    ->limit(12)
    ->all();

foreach ($books as $key => $book) {
    echo $key." - ".$book['title']."\n";
}
```