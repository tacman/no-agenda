import { test } from 'node:test';
import assert from 'node:assert/strict';
import { DateTime } from '../vendor/luxon/luxon.index.js';

import { currentlyRecording, nextRecording } from '../utilities/recording_time.js';

const recordingTimes = [
  [4, 11],
  [7, 11],
];

const startDay = 9;

for (let day = startDay; day < (startDay + 7); day++) {
  for (let hour = 0; hour < 24; hour++) {
    const date = DateTime.utc(2022, 10, day, hour);

    test(`currentlyRecording for day ${day}, hour ${hour}`, () => {
      const isRecording = ([9, 13].includes(day) && [18, 19, 20].includes(hour));

      assert.equal(currentlyRecording(date, recordingTimes), isRecording);
    });

    test(`nextRecording for day ${day}, hour ${hour}`, () => {
      const test = nextRecording(date, recordingTimes).toUTC().toISO();

      if (day === 9 && hour < 18) {
        assert.equal(test, '2022-10-09T18:00:00.000Z');
      } else if (day < 13 || (day === 13 && hour < 18)) {
        assert.equal(test, '2022-10-13T18:00:00.000Z');
      } else {
        assert.equal(test, '2022-10-16T18:00:00.000Z');
      }
    });
  }
}
