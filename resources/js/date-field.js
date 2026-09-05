/**
 * The `<x-date-field>` picker: separate year/month/day (and optional time)
 * boxes that write one hidden `Y-m-d\TH:i` input, so the save contract of the
 * `datetime-local` input it replaces stays identical.
 *
 * Segment order, month labels and the 12/24h clock are decided in PHP from the
 * locale and passed in; this file holds no locale knowledge.
 */

const pad = (value, length = 2) => String(value).padStart(length, '0');

/** Days in a month, leap-aware. Month is 1-12. */
export function daysInMonth(year, month) {
    if (!(month >= 1 && month <= 12)) {
        return 31;
    }

    // Day 0 of the next month is the last day of this one.
    return new Date(Date.UTC(year || 2001, month, 0)).getUTCDate();
}

/** Split a `Y-m-d\TH:i` value into segments; anything else gives empty segments. */
export function parseValue(value) {
    const match = /^(-?\d{1,6})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(value ?? '');

    if (!match) {
        return { year: '', month: '', day: '', hour: '', minute: '' };
    }

    return {
        year: String(Number(match[1])),
        month: String(Number(match[2])),
        day: String(Number(match[3])),
        hour: String(Number(match[4])),
        minute: match[5],
    };
}

/**
 * Build `Y-m-d\TH:i` from segments. Returns '' while the date is incomplete,
 * so a half-filled field posts empty and the server rules speak.
 */
export function composeValue({ year, month, day, hour, minute }) {
    const y = Number(year);
    const m = Number(month);
    const d = Number(day);

    if (!year || !month || !day || !Number.isInteger(y) || !(m >= 1 && m <= 12) || !(d >= 1)) {
        return '';
    }

    const h = Number(hour) || 0;
    const min = Number(minute) || 0;

    return `${pad(y, 4)}-${pad(m)}-${pad(Math.min(d, daysInMonth(y, m)))}T${pad(h)}:${pad(min)}`;
}

/** Keep only digits (and a leading minus for a year before year 1). */
export function digitsOnly(value, allowNegative = false) {
    const sign = allowNegative && String(value).startsWith('-') ? '-' : '';

    return sign + String(value).replace(/\D/g, '');
}

/**
 * Move a `Y-m-d\TH:i` value by one minute. Carries over the hour, the day, the
 * month and the year.
 */
export function stepMinute(value, delta) {
    const parts = parseValue(value);

    if (parts.year === '') {
        return value;
    }

    let year = Number(parts.year);
    let month = Number(parts.month);
    let day = Number(parts.day);
    let hour = Number(parts.hour);
    let minute = Number(parts.minute) + delta;

    if (minute > 59) {
        minute = 0;
        hour += 1;
    } else if (minute < 0) {
        minute = 59;
        hour -= 1;
    }

    if (hour > 23) {
        hour = 0;
        day += 1;
    } else if (hour < 0) {
        hour = 23;
        day -= 1;
    }

    if (day > daysInMonth(year, month)) {
        day = 1;
        month += 1;
    } else if (day < 1) {
        month -= 1;

        if (month < 1) {
            month = 12;
            year -= 1;
        }

        day = daysInMonth(year, month);
    }

    if (month > 12) {
        month = 1;
        year += 1;
    }

    return `${pad(year, 4)}-${pad(month)}-${pad(day)}T${pad(hour)}:${pad(minute)}`;
}

/**
 * Clamp a composed value into the bookend window. Strings compare correctly.
 *
 * The clamp stops one minute *inside* the window. A regular event that sits on
 * the same instant as Start or End sorts next to the bookend it belongs under,
 * which puts it before Start or after End in the event list.
 */
export function clampToWindow(value, min, max) {
    if (!value) {
        return value;
    }

    if (min && value <= min) {
        return stepMinute(min, 1);
    }

    if (max && value >= max) {
        return stepMinute(max, -1);
    }

    return value;
}

export function registerDateField(Alpine) {
    Alpine.data('dateField', (config) => ({
        year: '',
        month: '',
        day: '',
        hour: '',
        minute: '00',
        meridiem: 'AM',
        showTime: false,
        value: '',
        // '', 'min' or 'max': which bookend the typed date was moved onto.
        clampedTo: '',

        min: config.min || '',
        max: config.max || '',
        twelveHour: Boolean(config.twelveHour),

        init() {
            const parsed = parseValue(config.value);

            this.year = parsed.year;
            this.month = parsed.month;
            this.day = parsed.day;
            this.minute = parsed.minute || '00';
            this.setHourFromValue(Number(parsed.hour) || 0);

            // Midnight is the "no time given" edge, so the time row stays closed.
            this.showTime = parsed.year !== '' && !(Number(parsed.hour) === 0 && Number(parsed.minute) === 0);
            this.value = config.value || '';
        },

        setHourFromValue(hour) {
            if (!this.twelveHour) {
                this.hour = String(hour);

                return;
            }

            this.meridiem = hour >= 12 ? 'PM' : 'AM';
            this.hour = String(hour % 12 === 0 ? 12 : hour % 12);
        },

        /** The hour on a 24-hour clock, whichever clock the boxes show. */
        hour24() {
            const hour = Number(this.hour) || 0;

            if (!this.twelveHour) {
                return hour;
            }

            const base = hour % 12;

            return this.meridiem === 'PM' ? base + 12 : base;
        },

        addTime() {
            this.showTime = true;
        },

        clearTime() {
            this.showTime = false;
            this.hour = this.twelveHour ? '12' : '0';
            this.minute = '00';
            this.meridiem = 'AM';
            this.sync();
        },

        onYearInput(event) {
            this.year = digitsOnly(event.target.value, true);
            event.target.value = this.year;
            this.sync();
        },

        onDayInput(event) {
            this.day = digitsOnly(event.target.value);
            event.target.value = this.day;
            this.sync();
        },

        /** On blur the day settles inside the month it belongs to. */
        clampDay() {
            if (this.day === '') {
                return;
            }

            const last = daysInMonth(Number(this.year) || 2001, Number(this.month));

            this.day = String(Math.min(Math.max(Number(this.day), 1), last));
            this.sync();
        },

        sync() {
            const composed = composeValue({
                year: this.year,
                month: this.month,
                day: this.day,
                hour: this.showTime ? this.hour24() : 0,
                minute: this.showTime ? this.minute : 0,
            });

            // A soft hint only: WithinEventWindow stays authoritative on the server.
            this.value = clampToWindow(composed, this.min, this.max);
            this.clampedTo = this.whichBookend(composed);
        },

        /**
         * A half-typed year looks out of window on every keystroke, so the notice
         * waits for a full four digits.
         */
        whichBookend(composed) {
            if (!composed || digitsOnly(this.year).length < 4) {
                return '';
            }

            if (this.min && composed <= this.min) {
                return 'min';
            }

            if (this.max && composed >= this.max) {
                return 'max';
            }

            return '';
        },
    }));
}
