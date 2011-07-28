Requirements
============

The multirole filter requires PHP 5 or later and any release of Moodle 1.9.

Installation
============

Copy the filter/multirole directory into your Moodle installation's filter directory.

Configuration
=============

Enable the "Multirole" filter on your Moodle site.  For further help reference the [Moodle documentation on enabling filters](http://docs.moodle.org/19/en/Filters#Enabling_filters).

Use
===

To use the filter add one of two attributes to any html tag in a html block, topic summary, or other location that the filters affect.  To filter by a user's capability in the course use the "data-capability" attribute.  To filter by the short name of a role assigned to the user in the course use the "data-role" attribute.

Examples of filtering by capability
-----------------------------------

```html
<p data-capability="gradereport/grader:view">
Only users with permission to see the grader report will see the text in this paragraph.
</p>
```

```html
<p data-capability="mod/chat:chat">
Please, join our <a href="/mod/chat/view.php?id=7">chat room</a>!
</p>
```

```html
<img data-capability="gradereport/grader:view" src="/file.php/4/grader-report-link-help-image.png" />
```

Examples of filtering by role
-----------------------------

```html
<p data-role="editingteacher">
Only users with the "Teacher" will see the text in this paragraph.
</p>
```

```html
<img data-role="student" src="/file.php/4/welcome-student.png" />
```