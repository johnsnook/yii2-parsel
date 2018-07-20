Yii2 Parsel
==================
Allows developers to provide a boolean search query interface, similar to Google or Sphinx search or other full-text search (FTS) engines.

Turns a user query like '`georgia -(atlanta or decatur)`' into '`georgia AND NOT (atlanta or decatur)`' which is then turn into the follow SQL:

```sql
SELECT 
  "ip", /* ip address */
  "visits", /* how many requests they've made */
  "city", 
  "region" 
FROM 
/* A table similar to apaches access log.  See my extension yii2-ipFilter */
  "visitor" 
WHERE 
  (
    ("visitor"."ip" ILIKE '%georgia%') 
    OR ("visitor"."city" ILIKE '%georgia%') 
    OR ("visitor"."region" ILIKE '%georgia%')
  ) 
  AND ( /** marvel as we efortlessly generate a subquery */
    "ip" NOT IN (
      SELECT 
        "ip" 
      FROM 
        "visitor" 
      WHERE 
        (
          ("visitor"."ip" ILIKE '%atlanta%') 
          OR ("visitor"."city" ILIKE '%atlanta%') 
          OR ("visitor"."region" ILIKE '%atlanta%')
        ) 
        OR (
          ("visitor"."ip" ILIKE '%decatur%') 
          OR ("visitor"."city" ILIKE '%decatur%') 
          OR ("visitor"."region" ILIKE '%decatur%')
        )
    )
  )
```

```bash
╔════════════════╤════════╤═══════════════╤═════════╗
║ Ip             │ Visits │ City          │ region  ║
╟────────────────┼────────┼───────────────┼─────────╢
║ 107.77.232.216 │ 16     │               │ Georgia ║
╟────────────────┼────────┼───────────────┼─────────╢
║ 107.77.235.199 │ 3      │               │ Georgia ║
╟────────────────┼────────┼───────────────┼─────────╢
║ 174.218.142.27 │ 1      │ Lawrenceville │ Georgia ║
╟────────────────┼────────┼───────────────┼─────────╢
║ 107.77.233.225 │ 18     │               │ Georgia ║
╟────────────────┼────────┼───────────────┼─────────╢
║ 205.201.132.14 │ 42     │ Woodstock     │ Georgia ║
╟────────────────┼────────┼───────────────┼─────────╢
║ 192.3.160.15   │ 4      │ Douglas       │ Georgia ║
╚════════════════╧════════╧═══════════════╧═════════╝

```



Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist johnsnook/yii2-parsel "*"
```

or add

```
"johnsnook/yii2-parsel": "*"
```

to the require section of your `composer.json` file.

Usage
-----

> *"Look, I didn't know I could speak Parseltongue! What else don't I know about myself? Look. Maybe you can do something, even something horrible and not know you did it."*

Once the extension is installed, simply use it in your code by  :

```php
$userQuery = 'good AND plenty -licorice';
$query = Post::find()->select('name', 'description', 'content');
$query = \johnsnook\parsel\ParselQuery::build($query, $userQuery);
$query->all();
```

Tokens/behavior:
-----
Fields to be search must be either text, varchar or char currently.  Future versions may expand to number, dates and maybe even JSON.  All search terms, except where specified bye the full match operator are wrapped in your databases wildcard of choice.  Searching for "smart"  is equivalent to the SQL expression `'%smart%'`.  Search is case insensitive as long as your database's `LIKE` operator is.  PostgreSQL will use `ILIKE`.  

#### Conjunctives:

'AND' is the default behavior. "smart pretty" is the same as "smart AND pretty."

'OR' allows more results in your query:  "smart OR pretty."

#### Operators:

Negation: '-'.  The user query "smart pretty -judgmental" parses to "smart AND pretty AND NOT judgmental"

Sub-query : '()', Allows grouping of terms .  The user query "-crazy (smart AND pretty)" parses to "NOT crazy AND (smart AND pretty)".

Wildcard: '*', fuzzy matches.  "butt\*" matches butt, buttery, buttered etc.

Character wildcard: '_', matches one character.  "boo\_" matches boot, book, bool, boon, etc.

Full match: '=', field match.  Entire fields must be equal to the term.  "=georgia" only matches where one or more fields is exactly equal to the search term.  The search term will NOT be bracketed with %, but wildcards can still be used.

Phrase: double quotes.  '"Super fun"' searches for the full phrase, space include.  Wild cards, negation and exact match operators all work within the phrase.

Phrase, no wildcards: single quotes.  The term will not be evaluated for * or _, but will be wrapped in wildcards.  If a % or _ is in the term, it will be escaped.  'P%on*' becomes '%P\%on\*%'.

#### Examples



## Acknowledgements

This project was built by heavily modifying the excellent "[Search Query Parser](https://github.com/pimcore/search-query-parser)" project.  I de-abstracted the token structure and modified the parser class to better fit my needs.  Their licsence file should be found at the root of this project.

Both projects are made possible by the amazing and lightning quick [lexer library](https://github.com/nikic/Phlexy) by Nikita Popov of Berlin.  It's work reading his [article on the subject](http://nikic.github.io/2011/10/23/Improving-lexing-performance-in-PHP.html).