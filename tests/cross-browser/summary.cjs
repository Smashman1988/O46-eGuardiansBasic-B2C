const fs = require('node:fs');
const path = require('node:path');

// Keep blocked viewport/visibility measurements separate from unsupported input modes.
module.exports = class LocalBrowserSummary {
    onBegin(_config, suite) { this.suite = suite; }

    onEnd(run) {
        const projects = {};
        for (const test of this.suite.allTests()) {
            const project = test.parent.project().name;
            const counts = projects[project] ??= {planned:0, passed:0, failed:0, flaky:0, blocked:0, skipped:0, notRun:0, exceptions:[]};
            counts.planned++;
            const last = test.results.at(-1);
            const annotations = last?.annotations ?? test.annotations;
            let status = 'notRun';
            if (last && last.workerIndex >= 0) {
                if (last.status === 'skipped') status = annotations.some(a=>a.type==='blocked') ? 'blocked' : 'skipped';
                else if (test.outcome() === 'flaky') status = 'flaky';
                else status = last.status === 'passed' ? 'passed' : 'failed';
            }
            counts[status]++;
            if (status !== 'passed') counts.exceptions.push({title:test.title,status,annotations,error:last?.error?.message});
        }
        const file = path.resolve(__dirname,'../../.cache/cross-browser/summary.json');
        fs.mkdirSync(path.dirname(file),{recursive:true});
        fs.writeFileSync(file, JSON.stringify({status:run.status,projects},null,2)+'\n');
    }
};
