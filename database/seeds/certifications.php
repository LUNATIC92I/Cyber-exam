<?php
declare(strict_types=1);

/**
 * CYBER EXAM certification catalogue. Each entry maps to a question bank in
 * database/seeds/questions/<file>.php. Add a new certification by adding an
 * entry here and a matching question file (100+ questions).
 */
return [
    [
        'code' => 'SOC', 'slug' => 'soc-analyst', 'name' => 'SOC Analyst', 'accent' => 'blue', 'file' => 'soc',
        'tagline' => 'Monitor, triage and respond to threats inside a modern Security Operations Center.',
        'description' => 'Validates the skills of analysts working in a Security Operations Center: SIEM operations, alert triage, log analysis, incident response, threat intelligence, threat hunting and detection engineering mapped to MITRE ATT&CK.',
        'topics' => ['SOC operations', 'SIEM', 'Alert triage', 'Incident response', 'Log analysis', 'MITRE ATT&CK', 'Threat intelligence', 'Threat hunting', 'Detection engineering', 'Security monitoring'],
    ],
    [
        'code' => 'NETSEC', 'slug' => 'network-security', 'name' => 'Network Security', 'accent' => 'cyan', 'file' => 'network',
        'tagline' => 'Design, defend and monitor resilient enterprise networks.',
        'description' => 'Validates practical knowledge of TCP/IP, VLANs, firewalls, IDS/IPS, VPNs, segmentation, DNS security, network attacks and network monitoring.',
        'topics' => ['TCP/IP', 'VLAN', 'Firewall', 'IDS/IPS', 'VPN', 'Segmentation', 'DNS security', 'Network attacks', 'Network monitoring'],
    ],
    [
        'code' => 'ETH', 'slug' => 'ethical-hacking', 'name' => 'Ethical Hacking', 'accent' => 'violet', 'file' => 'ethical-hacking',
        'tagline' => 'Think like an attacker to find and report weaknesses before adversaries do.',
        'description' => 'Validates offensive security methodology within an authorised scope: reconnaissance, enumeration, vulnerability assessment, web security and OWASP risks, authentication attacks, exploitation, privilege escalation, post-exploitation and reporting.',
        'topics' => ['Reconnaissance', 'Enumeration', 'Vulnerability assessment', 'Web security', 'OWASP', 'Authentication attacks', 'Exploitation', 'Privilege escalation', 'Post-exploitation', 'Reporting'],
    ],
    [
        'code' => 'CLOUD', 'slug' => 'cloud-security', 'name' => 'Cloud Security', 'accent' => 'sky', 'file' => 'cloud',
        'tagline' => 'Secure identities, workloads and data across AWS and Azure.',
        'description' => 'Validates cloud security skills across AWS and Azure: IAM, logging, misconfiguration management, storage security, cloud networking, secrets management and container security.',
        'topics' => ['AWS', 'Azure', 'IAM', 'Cloud logging', 'Cloud misconfiguration', 'Storage security', 'Cloud networking', 'Secrets management', 'Containers'],
    ],
    [
        'code' => 'DFIR', 'slug' => 'digital-forensics', 'name' => 'Digital Forensics', 'accent' => 'teal', 'file' => 'forensics',
        'tagline' => 'Collect, preserve and analyse digital evidence with forensic rigour.',
        'description' => 'Validates forensic methodology: evidence collection, chain of custody, disk and memory forensics, Windows, Linux and browser artifacts, timeline analysis and forensic reporting.',
        'topics' => ['Evidence collection', 'Chain of custody', 'Disk forensics', 'Memory forensics', 'Windows artifacts', 'Linux artifacts', 'Browser artifacts', 'Timeline analysis', 'Forensic reporting'],
    ],
    [
        'code' => 'GRC', 'slug' => 'grc-governance', 'name' => 'GRC / Cybersecurity Governance', 'accent' => 'emerald', 'file' => 'grc',
        'tagline' => 'Govern cyber risk, compliance and resilience at organisational level.',
        'description' => 'Validates governance, risk and compliance expertise: risk management, ISO/IEC 27001, policies, controls, audit, compliance, business continuity, disaster recovery and third-party risk.',
        'topics' => ['Risk management', 'ISO 27001', 'Policies', 'Controls', 'Audit', 'Compliance', 'Business continuity', 'Disaster recovery', 'Third-party risk'],
    ],
    [
        'code' => 'ARCH', 'slug' => 'cybersecurity-architecture', 'name' => 'Cybersecurity Architecture', 'accent' => 'indigo', 'file' => 'architecture',
        'tagline' => 'Architect enterprise security from Zero Trust to strategy.',
        'description' => 'Validates the ability to design enterprise security: security architecture, Zero Trust, IAM and PAM, identity security, network architecture, security controls, enterprise security and security strategy.',
        'topics' => ['Security architecture', 'Zero Trust', 'IAM', 'PAM', 'Identity security', 'Network architecture', 'Security controls', 'Enterprise security', 'Security strategy'],
    ],
];
