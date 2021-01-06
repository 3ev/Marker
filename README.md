<h1>Marker</h1>

<h2>What Does It Do?</h2>
It's a simple tool to help analyse currently running process/scripts. It can report start/finish times for scripts, 
heck memory usage, and make it a lot easier to digest logs.

<h2>Usage</h2>
When creating a new instance, The required options are the name of the process you are trying to analyse, and the
Logger implementation you wish to use. 

```php
$marker = new Marker('Currently running script', new MonoLog $logger);
```
You can then start the marker at you desired point. Usually this should be at the beginning, before you start 
retrieving/uploading data. This will record what time it was started, and memory usage at that time.

Note: You can add any extra information you want to add, to any of the start/mark/finish methods. This will be logged
along with whatever the marker is also reporting.

```php
$marker->start();
```

At any point, in the script, you can set a mark. The Marker will make a note of how long the 
process has been running, and the memory in use, at that point.

```php
$marker->mark();
```

Once you feel you are done with the analysis, end the Marker, so that it can finalise it's report

```php
$marker->end();

```

You can also ask the Marker to check the memory in use, at that time. It won't log anything, but will check
to see if the memory in use is higher than it's current peak check. This is a useful method to see if a particular
section of code is using up a lot of memory, with needing to log that.

```php
$marker->checkMemoryUsage();
```

<h2>Where Does The Logging Go?</h2>
That is totally up to you. Marker will accept any psr-3 compliant logger, so be sure you have configured that.
