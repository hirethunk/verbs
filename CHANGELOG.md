# Changelog

All notable changes to `:package_name` will be documented in this file.

## v0.0.8.1 - 2024-01-23

### What's Changed

* Fix the mysql json order bug by @joshhanley in https://github.com/hirethunk/verbs/pull/53
* Check for `componentHook` before calling it by @inxilpro in https://github.com/hirethunk/verbs/pull/54

**Full Changelog**: https://github.com/hirethunk/verbs/compare/0.0.8...0.0.8.1

## v0.0.8 - 2024-01-16

### What's Changed

* Refactor event/pending event, and add a way to immediately commit an event by @inxilpro in https://github.com/hirethunk/verbs/pull/27
* Add ability to include metadata on every event by @skylerkatz in https://github.com/hirethunk/verbs/pull/14
* Make `fire` do nothing while replaying by @jdiddydave in https://github.com/hirethunk/verbs/pull/35
* Fix typo in ids.md by @morpheus7CS in https://github.com/hirethunk/verbs/pull/39
* Clean up serialization by @inxilpro in https://github.com/hirethunk/verbs/pull/45
* Add id_type config by @jdiddydave in https://github.com/hirethunk/verbs/pull/44
* Prevent model serialization by @inxilpro in https://github.com/hirethunk/verbs/pull/47
* When deserializing, skip deserializing if source is already the destination type. by @DanielCoulbourne in https://github.com/hirethunk/verbs/pull/50
* Test Affordances by @DanielCoulbourne in https://github.com/hirethunk/verbs/pull/41
* State aliases by @inxilpro in https://github.com/hirethunk/verbs/pull/48
* Fix auto-discovery exception messages by @matthewpaulking in https://github.com/hirethunk/verbs/pull/49
* Bump aglipanci/laravel-pint-action from 2.3.0 to 2.3.1 by @dependabot in https://github.com/hirethunk/verbs/pull/51
* Add isAllowed() and isValid() methods for PendingEvent by @DanielCoulbourne in https://github.com/hirethunk/verbs/pull/52

### New Contributors

* @jdiddydave made their first contribution in https://github.com/hirethunk/verbs/pull/35
* @morpheus7CS made their first contribution in https://github.com/hirethunk/verbs/pull/39
* @matthewpaulking made their first contribution in https://github.com/hirethunk/verbs/pull/49

**Full Changelog**: https://github.com/hirethunk/verbs/compare/0.0.7...0.0.8

## v0.0.7 - 2023-12-07

### What's Changed

* fix typo by @gpibarra in https://github.com/hirethunk/verbs/pull/18
* Stop examples and workbench dirs being in the packagist repo by @morrislaptop in https://github.com/hirethunk/verbs/pull/23
* Add Livewire support to commit Verbs just before render by @joshhanley in https://github.com/hirethunk/verbs/pull/20
* Remove redundant "it it" from unit tests by @markjaquith in https://github.com/hirethunk/verbs/pull/30
* Remove phases from events by @inxilpro in https://github.com/hirethunk/verbs/pull/33
* Better handling of "stateless" events and singleton states by @inxilpro in https://github.com/hirethunk/verbs/pull/32
* Fix testing setup for MySQL by @inxilpro in https://github.com/hirethunk/verbs/pull/34
* Postgres support by @morrislaptop in https://github.com/hirethunk/verbs/pull/28

### New Contributors

* @gpibarra made their first contribution in https://github.com/hirethunk/verbs/pull/18
* @morrislaptop made their first contribution in https://github.com/hirethunk/verbs/pull/23
* @joshhanley made their first contribution in https://github.com/hirethunk/verbs/pull/20
* @markjaquith made their first contribution in https://github.com/hirethunk/verbs/pull/30

**Full Changelog**: https://github.com/hirethunk/verbs/compare/0.0.6...0.0.7

## v0.0.6 - 2023-11-22

### What's Changed

- Add support for interfaces in the expectsParameters check on MethodFi… by @DanielCoulbourne in https://github.com/hirethunk/verbs/pull/19

**Full Changelog**: https://github.com/hirethunk/verbs/compare/0.0.5...v0.0.6

## v0.0.5 - 2023-11-20

Fixes support for empty collection serialization.

## v0.0.4 - 2023-11-19

This adds some improvements to how Laravel collections are serialized in Verbs.

## v0.0.3 - 2023-11-17

Addresses some issues with the concurrency guards.

## v0.0.1 - 2023-11-16

Initial release
