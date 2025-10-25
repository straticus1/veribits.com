#!/usr/bin/env python3
"""
VeriBits CLI - Command-line interface for VeriBits security and developer tools
"""

from setuptools import setup, find_packages

with open("README.md", "r", encoding="utf-8") as fh:
    long_description = fh.read()

setup(
    name="veribits",
    version="1.0.0",
    author="After Dark Systems, LLC",
    author_email="support@afterdarksys.com",
    description="Command-line interface for VeriBits security and developer tools",
    long_description=long_description,
    long_description_content_type="text/markdown",
    url="https://github.com/afterdarksystems/veribits-cli",
    packages=find_packages(),
    classifiers=[
        "Programming Language :: Python :: 3",
        "Programming Language :: Python :: 3.8",
        "Programming Language :: Python :: 3.9",
        "Programming Language :: Python :: 3.10",
        "Programming Language :: Python :: 3.11",
        "License :: OSI Approved :: MIT License",
        "Operating System :: OS Independent",
        "Development Status :: 5 - Production/Stable",
        "Intended Audience :: Developers",
        "Topic :: Security",
        "Topic :: Software Development :: Testing",
    ],
    python_requires=">=3.8",
    install_requires=[
        "requests>=2.28.0",
        "click>=8.1.0",
        "rich>=13.0.0",
        "pyyaml>=6.0",
        "python-dotenv>=1.0.0",
    ],
    entry_points={
        "console_scripts": [
            "veribits=veribits.cli:main",
            "vb=veribits.cli:main",  # Short alias
        ],
    },
)
