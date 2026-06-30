import { readFileSync, readdirSync } from 'fs';
const files = readdirSync('public/build/assets').filter(f => f.endsWith('.js'));
let found = false;
for (const f of files) {
  const c = readFileSync('public/build/assets/' + f, 'utf8');
  if (c.includes('uploadPlatformAvatar') || c.includes('submitPlatformAi')) {
    console.log('FOUND in:', f.substring(0, 60));
    found = true;
  }
}
if (!found) {
  // Check if the file contains user-chat related imports
  for (const f of files) {
    const c = readFileSync('public/build/assets/' + f, 'utf8');
    if (c.includes('user-chat') || c.includes('userChat')) {
      console.log('user-chat found in:', f.substring(0, 60));
    }
  }
}
