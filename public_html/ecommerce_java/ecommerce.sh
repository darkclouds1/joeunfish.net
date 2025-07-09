#!/bin/sh

# Java 홈 디렉토리 설정
export JAVA_HOME=/usr/lib/jvm/java-11-openjdk

# e-commerce 애플리케이션 홈 디렉토리 설정
export EC_HOME=/home/joeunfish.net/public_html/ecommerce_java

# 실행 경로에 Java 실행 파일들 추가
export PATH=$JAVA_HOME/bin:$PATH

# Java 애플리케이션 실행 
java -Djava.class.path=$EC_HOME:$EC_HOME/bin:$EC_HOME/lib/log4j-1.2.8.jar:$EC_HOME/lib/ojdbc14.jar:$EC_HOME/lib/commons-io-1.4.jar net.dacom.CommMain $EC_HOME/ecommerce_rcv.properties
