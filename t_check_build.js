const fs = require('fs');
const files = fs.readdirSync('public/build/assets').filter(f => f.endsWith('.js'));
console.log('Build files:', files.length);
let found = false;
for (const f of files) {
  const c = fs.readFileSync('public/build/assets/' + f, 'utf8');
  if (c.includes('uploadPlatformAvatar')) {
    console.log('FOUND in:', f);
    found = true;
    break;
  }
}
if (!found) console.log('NOT FOUND - build may be stale');
