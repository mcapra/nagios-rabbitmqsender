import pika
import argparse
	
if __name__ == '__main__':

	parser = argparse.ArgumentParser(add_help = True, description = "Sends messages to a RabbitMQ server.")

	parser.add_argument('-H', '--host', action='store', help='The host name or logical address of the remote RabbitMQ server.', required=True)
	parser.add_argument('-P', '--port', action='store', help='The port on which we will connect to the RabbitMQ server.', required=True)
	parser.add_argument('-q', '--queue', action='store', help='The the name of the queue in RabbitMQ server we wish to send our message to.', required=True)
	parser.add_argument('-m', '--message', action='store', help='The message we will send to the RabbitMQ server. Be mindful of CLI escaping!', required=True)
	parser.add_argument('-u', '--username', action='store', help='The username of the remote RabbitMQ account (optional).', required=False)
	parser.add_argument('-p', '--password', action='store', help='The password of the remote RabbitMQ account (optional).', required=False)
	parser.add_argument('-v', '--verbose', action='store_true', help='Print extra debug information. Don\'t include this in your check_command definition!', default=False)

	args = parser.parse_args()
	
	try:
		if ((args.username is not None) or (args.password is not None)):
			if((args.username is not None) and (args.password is not None)):
				credentials = pika.PlainCredentials(args.username, args.password)
				connection = pika.BlockingConnection(pika.ConnectionParameters(args.host,int(args.port),'/',credentials))
			else:
				print('Both a username and password is required! Please enter both, not one or the other!')
				exit(2)
		else:
			connection = pika.BlockingConnection(pika.ConnectionParameters(args.host,int(args.port),'/'))
		
		channel = connection.channel()

		channel.queue_declare(queue=args.queue)

		channel.basic_publish(exchange='',
							  routing_key=args.queue,
							  body=args.message)
		print("Sent: " + args.message)
		connection.close()
	except Exception, e:
		logging.error(str(e))
		try:
			dcom.disconnect()
		except:
			pass
	