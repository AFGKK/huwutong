// HWT License — Jenkins Pipeline Library
// 在 Jenkins Pipeline 中获取/激活 HWT License

/**
 * Fetch HWT License from API
 * @param args.token CI/CD API Token
 * @param args.apiUrl API base URL (optional)
 */
def hwtFetchLicense(Map args) {
  def token = args.token ?: env.HWT_CI_TOKEN
  def apiUrl = args.apiUrl ?: 'https://api.huwutong.com'

  if (!token) {
    error 'HWT_CI_TOKEN is required'
  }

  def response = sh(
    script: """
      curl -s -X GET '${apiUrl}/api/ci/license/fetch' \
        -H 'Authorization: Bearer ${token}' \
        -H 'User-Agent: hwt-jenkins/1.0'
    """,
    returnStdout: true
  ).trim()

  def json = readJSON text: response
  if (!json.license_key) {
    error "Failed to fetch license: ${json.error ?: 'unknown'}"
  }

  env.HWT_LICENSE_KEY = json.license_key
  echo "License fetched: ${json.license_key}"
  return json
}

/**
 * Activate HWT License
 * @param args.token CI/CD API Token
 * @param args.licenseKey License key to activate
 * @param args.apiUrl API base URL (optional)
 */
def hwtActivateLicense(Map args) {
  def token = args.token ?: env.HWT_CI_TOKEN
  def licenseKey = args.licenseKey ?: env.HWT_LICENSE_KEY
  def apiUrl = args.apiUrl ?: 'https://api.huwutong.com'

  if (!token) {
    error 'HWT_CI_TOKEN is required'
  }
  if (!licenseKey) {
    error 'licenseKey is required'
  }

  def response = sh(
    script: """
      curl -s -X POST '${apiUrl}/api/ci/license/activate' \
        -H 'Authorization: Bearer ${token}' \
        -H 'Content-Type: application/json' \
        -d '{"license_key": "${licenseKey}"}'
    """,
    returnStdout: true
  ).trim()

  def json = readJSON text: response
  echo "License activated: ${licenseKey} (status: ${json.status})"
  return json
}

// Export functions for Pipeline usage
return this