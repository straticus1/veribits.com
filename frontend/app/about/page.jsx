export default function AboutPage() {
  return (
    <div className="min-h-screen bg-dark-900">
      {/* Header Section */}
      <div className="bg-gradient-brand py-20">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <h1 className="text-5xl font-bold text-white mb-6">
            About VeriBits
          </h1>
          <p className="text-xl text-gray-200 max-w-3xl">
            Verifying the smallest things at scale. From files to emails to micro-transactions,
            VeriBits provides enterprise-grade verification services powered by After Dark Systems.
          </p>
        </div>
      </div>

      {/* Content Sections */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        {/* Mission Section */}
        <div className="mb-16">
          <h2 className="text-3xl font-bold text-white mb-6">Our Mission</h2>
          <div className="card">
            <p className="text-gray-300 text-lg leading-relaxed mb-4">
              At VeriBits, we believe trust is built on verification. In an increasingly digital world,
              the ability to verify authenticity, integrity, and security is paramount.
            </p>
            <p className="text-gray-300 text-lg leading-relaxed">
              Our platform provides comprehensive verification services that help businesses and
              individuals establish trust, prevent fraud, and ensure compliance across all digital interactions.
            </p>
          </div>
        </div>

        {/* What We Verify */}
        <div className="mb-16">
          <h2 className="text-3xl font-bold text-white mb-6">What We Verify</h2>
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div className="card-hover">
              <div className="text-brand-green text-2xl mb-3">🔒</div>
              <h3 className="text-xl font-semibold text-white mb-2">Files & Documents</h3>
              <p className="text-gray-400">
                SHA256 hash verification, malware scanning with ClamAV, and document integrity checks.
              </p>
            </div>

            <div className="card-hover">
              <div className="text-brand-green text-2xl mb-3">📧</div>
              <h3 className="text-xl font-semibold text-white mb-2">Email Addresses</h3>
              <p className="text-gray-400">
                Comprehensive email verification, deliverability checks, and spam detection.
              </p>
            </div>

            <div className="card-hover">
              <div className="text-brand-green text-2xl mb-3">🌐</div>
              <h3 className="text-xl font-semibold text-white mb-2">DNS & Domains</h3>
              <p className="text-gray-400">
                Complete DNS health checks, DNSSEC validation, propagation verification, and blacklist monitoring.
              </p>
            </div>

            <div className="card-hover">
              <div className="text-brand-green text-2xl mb-3">🔐</div>
              <h3 className="text-xl font-semibold text-white mb-2">SSL Certificates</h3>
              <p className="text-gray-400">
                Certificate analysis, expiry monitoring, and certificate-key pair validation.
              </p>
            </div>

            <div className="card-hover">
              <div className="text-brand-green text-2xl mb-3">📦</div>
              <h3 className="text-xl font-semibold text-white mb-2">Archive Files</h3>
              <p className="text-gray-400">
                Safe archive inspection without extraction, detecting zip bombs and malicious content.
              </p>
            </div>

            <div className="card-hover">
              <div className="text-brand-green text-2xl mb-3">🪪</div>
              <h3 className="text-xl font-semibold text-white mb-2">Government IDs</h3>
              <p className="text-gray-400">
                AI-powered identity verification with facial recognition and document authentication.
              </p>
            </div>
          </div>
        </div>

        {/* Technology Stack */}
        <div className="mb-16">
          <h2 className="text-3xl font-bold text-white mb-6">Built with Excellence</h2>
          <div className="card">
            <div className="grid md:grid-cols-2 gap-8">
              <div>
                <h3 className="text-xl font-semibold text-brand-amber mb-4">Enterprise Infrastructure</h3>
                <ul className="space-y-2 text-gray-300">
                  <li>• AWS ECS Fargate for scalable containers</li>
                  <li>• PostgreSQL for reliable data storage</li>
                  <li>• Redis for high-performance caching</li>
                  <li>• CloudWatch for comprehensive monitoring</li>
                </ul>
              </div>
              <div>
                <h3 className="text-xl font-semibold text-brand-amber mb-4">Security First</h3>
                <ul className="space-y-2 text-gray-300">
                  <li>• JWT authentication with Cognito</li>
                  <li>• Rate limiting and quota management</li>
                  <li>• Encrypted data at rest and in transit</li>
                  <li>• GDPR/CCPA compliant</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        {/* About After Dark Systems */}
        <div>
          <h2 className="text-3xl font-bold text-white mb-6">Powered by After Dark Systems, LLC</h2>
          <div className="card">
            <p className="text-gray-300 text-lg leading-relaxed mb-4">
              VeriBits is a product of <span className="text-brand-amber font-semibold">After Dark Systems, LLC</span>,
              a technology company specializing in security, verification, and identity services.
            </p>
            <p className="text-gray-300 text-lg leading-relaxed">
              Our suite of products includes enterprise identity verification, secure communication platforms,
              and advanced verification APIs that power trust across the internet.
            </p>
            <div className="mt-6 flex gap-4">
              <a href="https://www.aeims.app" target="_blank" rel="noopener noreferrer" className="link">
                Visit AEIMS →
              </a>
              <a href="https://idverify.aeims.app" target="_blank" rel="noopener noreferrer" className="link">
                ID Verification Service →
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
