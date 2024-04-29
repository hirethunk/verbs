document.addEventListener("livewire:init", () => {
    let verbsScripts = document.querySelector('[verbs\\:events]');

    if (!verbsScripts) {
        console.warn('Livewire Verbs: No Verbs events found in the DOM.')

        return
    }

    // Get the value of the 'verbs-events' attribute
    let verbsEventsEncoded = verbsScripts.getAttribute('verbs:events')

    let Verbs = {}
    window.Verbs = Verbs

    console.log(verbsEventsEncoded, JSON.parse(verbsEventsEncoded))

    let eventData = JSON.parse(verbsEventsEncoded)

    Verbs.events = eventData.events
    Verbs.stateEvents = eventData.stateEvents
    Verbs.eventsEncoded = verbsEventsEncoded

    Livewire.hook("request", ({ uri, options, payload, respond, succeed, fail }) => {
        let body = JSON.parse(options.body)

        body.verbs = { eventsEncoded: Verbs.eventsEncoded }

        options.body = JSON.stringify(body)

        succeed(({ status, json }) => {
            Verbs.events = json.verbs.events
            Verbs.stateEvents = json.verbs.stateEvents
            Verbs.eventsEncoded = json.verbs.eventsEncoded

            console.log("Verbs", Verbs)
        })
    })

    Livewire.directive("verbs", ({ el, directive, component, cleanup }) => {
        let { expression, modifiers } = directive

        if (!expression) {
            console.error("The `wire:verbs` directive requires an expression.")
        }

        let [getValue, setValue] = expression.split(",").map((verb) => verb.trim())

        console.log("Verbs", getValue, setValue)

        Alpine.bind(el, {
            ["x-model"]() {
                return {
                    get() {
                        return dataGet(component.$wire, getValue)
                    },
                    set(value) {
                        dataSet(component.$wire, setValue, value)
                    },
                }
            },
        })
    })
})

// Copied from vendor/livewire/livewire/js/utils.js
function dataGet(object, key) {
    if (key === "") return object

    return key.split(".").reduce((carry, i) => {
        if (carry === undefined) return undefined

        return carry[i]
    }, object)
}

// Copied from vendor/livewire/livewire/js/utils.js
function dataSet(object, key, value) {
    let segments = key.split(".")

    if (segments.length === 1) {
        return (object[key] = value)
    }

    let firstSegment = segments.shift()
    let restOfSegments = segments.join(".")

    if (object[firstSegment] === undefined) {
        object[firstSegment] = {}
    }

    dataSet(object[firstSegment], restOfSegments, value)
}
