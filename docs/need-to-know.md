Verbs is generally very simple to use. But there are a few things that you really need to know to
get started using event sourcing in Verbs.

## The "Rules"

### Rule 1: ID === Global Identity

In Verbs, IDs are **global identity**. This means that whatever IDs you use, they should be completely
unique regardless of what database you happen to be using. The easiest way to do that is to use
**Snowflakes**, which are unique IDs that also fit into a traditional database's "big int" primary key.

### Rule 2: Keep your events separate from your models
